<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Queues and tracks editorial messages. No webhook or message is stored in the journal. */
class BlackFury_Relay_Delivery {
	const EVENT = 'blackfury_relay_deliver';
	const JOB = '_blackfury_relay_delivery';
	const OVERRIDE = '_blackfury_relay_override';
	const RULE = '_blackfury_relay_channel';
	const CHANNELS = 'blackfury_relay_channels';
	const DEFAULT_CHANNEL = 'blackfury_relay_default';

	private $posts;

	public function __construct( BlackFury_Relay_Plugin_Post $posts ) {
		$this->posts = $posts;
		add_action( 'wp_after_insert_post', array( $this, 'enqueue' ), 10, 4 );
		add_action( 'init', array( self::class, 'register_editor_meta' ) );
		add_action( self::EVENT, array( $this, 'deliver' ) );
		if ( is_admin() ) {
			new BlackFury_Relay_Dashboard( $this, $posts );
		}
	}

	public static function register_editor_meta() {
		register_post_meta( 'post', self::OVERRIDE, array(
			'type' => 'string',
			'single' => true,
			'default' => 'auto',
			'sanitize_callback' => 'sanitize_key',
			'auth_callback' => static function ( $allowed, $key, $post_id ) {
				return current_user_can( 'edit_post', $post_id );
			},
			'show_in_rest' => array( 'schema' => array( 'context' => array( 'edit' ) ) ),
		) );
	}

	public static function is_webhook( $url, $allow_legacy = false ) {
		$parts = is_string( $url ) ? wp_parse_url( $url ) : false;
		if ( ! $parts || ( $parts['scheme'] ?? '' ) !== 'https' || empty( $parts['host'] )
			|| isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) ) {
			return false;
		}
		if ( $allow_legacy ) {
			return true; // wp_safe_remote_post still validates the destination and disallows redirects.
		}
		return ! isset( $parts['port'] ) && ! isset( $parts['query'] )
			&& in_array( strtolower( $parts['host'] ), array( 'discord.com', 'discordapp.com' ), true )
			&& preg_match( '~^/api/(?:v[0-9]+/)?webhooks/[0-9]{17,20}/[A-Za-z0-9_-]{20,200}/?$~D', $parts['path'] ?? '' );
	}

	public static function channels() {
		$channels = get_option( self::CHANNELS, array() );
		return is_array( $channels ) ? $channels : array();
	}

	public function destination( WP_Post $post, $override = null ) {
		$channels = self::channels();
		$override = $override ?? get_post_meta( $post->ID, self::OVERRIDE, true );
		if ( 'off' === $override ) {
			return array( 'id' => 'off', 'label' => __( 'No Discord notification', 'blackfury-relay' ) );
		}
		if ( '' !== $override && 'auto' !== $override ) {
			return $this->named_destination( $override, $channels );
		}
		$terms = wp_get_post_categories( $post->ID, array( 'orderby' => 'term_id', 'order' => 'ASC' ) );
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$route = get_term_meta( $term, self::RULE, true );
			if ( $route ) {
				return $this->named_destination( $route, $channels );
			}
		}
		$default = get_option( self::DEFAULT_CHANNEL, '' );
		if ( $default ) {
			return $this->named_destination( $default, $channels );
		}
		$legacy = new BlackFury_Relay_Plugin_HTTP( 'post' );
		return array( 'id' => 'legacy', 'label' => __( 'Existing webhook', 'blackfury-relay' ), 'url' => $legacy->get_webhook_url() );
	}

	private function named_destination( $id, array $channels ) {
		if ( ! isset( $channels[ $id ] ) ) {
			return array( 'id' => $id, 'label' => __( 'Unavailable channel', 'blackfury-relay' ) );
		}
		return array( 'id' => $id ) + $channels[ $id ];
	}

	public function enqueue( $id, WP_Post $post, $update, $before ) {
		// Core calls this after REST meta and terms are saved, including for scheduled articles.
		if ( BlackFury_Relay_Plugin::delivery_paused() || 'publish' !== $post->post_status || ( $before && 'publish' === $before->post_status ) || 'post' !== $post->post_type
			|| get_post_meta( $id, '_blackfury_relay_published', true )
			|| ! apply_filters( 'blackfury_relay_post_is_new_post', true, $post ) ) {
			return;
		}
		$this->with_lock( $post->ID, function () use ( $post ) {
			if ( get_post_meta( $post->ID, self::JOB, true ) ) {
				return;
			}
			$this->schedule( $post->ID, array( 'attempts' => 0 ), time() );
		} );
	}

	public function retry( $id, $accept_uncertainty ) {
		return $this->with_lock( $id, function () use ( $id, $accept_uncertainty ) {
			$job = get_post_meta( $id, self::JOB, true );
			if ( ! $job || get_post_meta( $id, '_blackfury_relay_published', true ) ) {
				return false;
			}
			if ( in_array( $job['status'], array( 'pending', 'sending' ), true ) ) {
				if ( wp_next_scheduled( self::EVENT, array( $id ) ) ) {
					return false;
				}
				// For interrupted sends, the worker will move sending to uncertain without emitting anything.
				if ( 'sending' === $job['status'] ) {
					return true === wp_schedule_single_event( time(), self::EVENT, array( $id ), true );
				}
				return $this->schedule( $id, $job, max( time(), (int) $job['next'] ) );
			}
			if ( ! in_array( $job['status'], array( 'failed', 'uncertain' ), true )
				|| ( $job['retry_not_before'] ?? 0 ) > time()
				|| ( 'uncertain' === $job['status'] && ! $accept_uncertainty ) ) {
				return false;
			}
			$job['attempts'] = 0;
			unset( $job['destination_hash'], $job['route'], $job['label'], $job['reason'], $job['code'], $job['retry_not_before'] );
			return $this->schedule( $id, $job, time() );
		} );
	}

	public function deliver( $id ) {
		$result = $this->with_lock( $id, function () use ( $id ) {
			$job = get_post_meta( $id, self::JOB, true );
			if ( ! $job ) {
				return;
			}
			if ( 'sending' === $job['status'] ) {
				$this->finish( $id, $job, 'uncertain', 'interrupted' );
				return;
			}
			if ( 'pending' !== $job['status'] || $job['next'] > time() ) {
				return;
			}
			if ( BlackFury_Relay_Plugin::delivery_paused() ) {
				$this->schedule( $id, $job, time() + 300 );
				return;
			}
			$post = get_post( $id );
			if ( ! $post || 'publish' !== $post->post_status || '' !== $post->post_password
				|| get_post_meta( $id, '_blackfury_relay_published', true ) ) {
				$this->finish( $id, $job, 'skipped', 'not_eligible' );
				return;
			}
			$route = $this->destination( $post );
			$job['label'] = $route['label'];
			if ( 'off' === $route['id'] ) {
				$this->finish( $id, $job, 'skipped', 'article_opt_out' );
				return;
			}
			$url = $route['url'] ?? '';
			if ( ! self::is_webhook( $url, 'legacy' === $route['id'] ) ) {
				$this->finish( $id, $job, 'failed', 'invalid_destination' );
				return;
			}
			$hash = hash_hmac( 'sha256', $url, wp_salt( 'auth' ) );
			if ( isset( $job['destination_hash'] ) && ( $job['route'] !== $route['id'] || ! hash_equals( $job['destination_hash'], $hash ) ) ) {
				$this->finish( $id, $job, 'failed', 'destination_changed' );
				return;
			}
			$job['route'] = $route['id'];
			$job['destination_hash'] = $hash;
			$job['status'] = 'sending';
			$job['updated'] = time();
			++$job['attempts'];
			wp_clear_scheduled_hook( self::EVENT, array( $id ) );
			if ( true !== wp_schedule_single_event( time() + 330, self::EVENT, array( $id ), true ) ) {
				$this->finish( $id, $job, 'failed', 'scheduler' );
				return;
			}
			if ( ! update_post_meta( $id, self::JOB, $job ) ) {
				$this->finish( $id, $job, 'failed', 'storage' );
				return;
			}
			$card = $this->posts->preview( $post, ! blackfury_relay_post_is_embed_enabled() );
			$http = new BlackFury_Relay_Plugin_HTTP( 'post' );
			$response = $http->relay( $url, $card['content'], $card['embed'], 'yes' === get_option( 'blackfury_relay_post_mention_everyone' ), $route['color'] ?? '' );
			$code = (int) wp_remote_retrieve_response_code( $response );
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			$message = $data['id'] ?? '';
			$channel = $data['channel_id'] ?? '';
			$job['code'] = $code;
			if ( $code >= 200 && $code < 300 && self::is_id( $message ) ) {
				$job['message'] = $message;
				$job['channel'] = self::is_id( $channel ) ? $channel : '';
				$guild = $data['guild_id'] ?? ( $route['guild'] ?? '' );
				$job['guild'] = self::is_id( $guild ) ? $guild : '';
				update_post_meta( $id, '_blackfury_relay_published', 'yes' );
				$this->finish( $id, $job, 'accepted' );
			} elseif ( 429 === $code ) {
				$header = wp_remote_retrieve_header( $response, 'retry-after' );
				$delay = max( is_numeric( $header ) ? (float) $header : 0, is_numeric( $data['retry_after'] ?? null ) ? (float) $data['retry_after'] : 0 );
				$next = time() + max( 30, (int) ceil( min( $delay, PHP_INT_MAX / 2 ) ) );
				if ( $delay > DAY_IN_SECONDS || $job['attempts'] >= 3 ) {
					$job['retry_not_before'] = $next;
					$this->finish( $id, $job, 'failed', 'rate_limit' );
				} else {
					$this->schedule( $id, $job, $next );
				}
			} elseif ( $code >= 400 && $code < 500 && 408 !== $code ) {
				$this->finish( $id, $job, 'failed', 'rejected' );
			} elseif ( is_wp_error( $response ) && in_array( $response->get_error_code(), array( 'relay_invalid_destination', 'relay_invalid_message' ), true ) ) {
				$this->finish( $id, $job, 'failed', $response->get_error_code() );
			} else {
				$this->finish( $id, $job, 'uncertain', 'unconfirmed' );
			}
		} );
		if ( false === $result && ! wp_next_scheduled( self::EVENT, array( $id ) ) ) {
			// Cron consumes its event before invoking us. Restore it if another process held the lock.
			wp_schedule_single_event( time() + 330, self::EVENT, array( $id ) );
		}
	}

	public static function is_id( $value ) {
		return is_string( $value ) && preg_match( '/^[0-9]{17,20}$/D', $value );
	}

	private function schedule( $id, array $job, $next ) {
		$job['status'] = 'pending';
		$job['next'] = $next;
		$job['updated'] = time();
		wp_clear_scheduled_hook( self::EVENT, array( $id ) );
		if ( true !== wp_schedule_single_event( $next, self::EVENT, array( $id ), true ) ) {
			$this->finish( $id, $job, 'failed', 'scheduler' );
			return false;
		}
		update_post_meta( $id, self::JOB, $job );
		return true;
	}

	private function finish( $id, array $job, $status, $reason = '' ) {
		$job['status'] = $status;
		$job['reason'] = $reason;
		$job['updated'] = time();
		unset( $job['next'] );
		update_post_meta( $id, self::JOB, $job );
		wp_clear_scheduled_hook( self::EVENT, array( $id ) );
	}

	private function with_lock( $id, callable $callback ) {
		global $wpdb;
		$key = 'blackfury_relay_lock_' . absint( $id );
		$lease = ( time() + 300 ) . ':' . wp_generate_uuid4();
		// The unique option_name index makes this claim atomic across cron workers.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value < %s", $key, (string) time() ) );
		$claimed = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, $lease ) );
		if ( 1 !== $claimed ) {
			return false;
		}
		try {
			return $callback();
		} finally {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, $lease ) );
		}
	}
}
