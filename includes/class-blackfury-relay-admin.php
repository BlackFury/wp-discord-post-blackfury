<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** WordPress settings, per-article controls, and delivery journal. */
class BlackFury_Relay_Dashboard {
	const PAGE = 'blackfury-relay';
	const ACTION = 'blackfury_relay_manage';

	private $relay;
	private $posts;

	public function __construct( BlackFury_Relay_Delivery $relay, BlackFury_Relay_Plugin_Post $posts ) {
		$this->relay = $relay;
		$this->posts = $posts;
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'block_editor' ) );
		add_action( 'add_meta_boxes_post', array( $this, 'editor' ) );
		add_action( 'save_post_post', array( $this, 'save_article' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'manage' ) );
	}

	public function menu() {
		add_menu_page( 'BlackFury Relay', 'BlackFury Relay', 'manage_options', self::PAGE, array( $this, 'screen' ), 'dashicons-megaphone', 60 );
	}

	public function assets( $hook ) {
		$screen = get_current_screen();
		if ( 'toplevel_page_' . self::PAGE !== $hook && ( ! $screen || 'post' !== $screen->base || 'post' !== $screen->post_type ) ) {
			return;
		}
		wp_enqueue_style( 'blackfury-relay', plugins_url( 'assets/relay.css', BLACKFURY_RELAY_FILE ), array(), '2.2.0-beta.2' );
	}

	public function editor() {
		$screen = get_current_screen();
		if ( $screen && $screen->is_block_editor() ) {
			return;
		}
		add_meta_box( 'blackfury-relay-article', __( 'BlackFury Relay · Discord', 'blackfury-relay' ), array( $this, 'article' ), 'post', 'normal' );
	}

	public function block_editor() {
		global $post;
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$channels = array();
		foreach ( BlackFury_Relay_Delivery::channels() as $id => $channel ) {
			$channels[] = array( 'value' => $id, 'label' => $channel['label'], 'color' => $channel['color'] );
		}
		$route = $this->relay->destination( $post, 'auto' );
		$card = $this->posts->preview( $post, ! blackfury_relay_post_is_embed_enabled() );
		$settings = array(
			'meta' => BlackFury_Relay_Delivery::OVERRIDE,
			'channels' => $channels,
			'route' => array( 'label' => $route['label'], 'color' => $route['color'] ?? '' ),
			'card' => array(
				'content' => wp_strip_all_tags( html_entity_decode( $card['content'], ENT_QUOTES, 'UTF-8' ) ),
				'embed' => $card['embed'] ? array(
					'title' => $card['embed']['title'],
					'description' => $card['embed']['description'],
					'image' => esc_url_raw( $card['embed']['image'] ?? '' ),
				) : false,
			),
		);
		$url = plugins_url( 'assets/relay-editor.js', BLACKFURY_RELAY_FILE );
		wp_enqueue_script( 'blackfury-relay-editor', $url, array( 'wp-plugins', 'wp-data', 'wp-element', 'wp-components', 'wp-editor', 'wp-edit-post', 'wp-i18n' ), '2.2.0-beta.2', true );
		wp_add_inline_script( 'blackfury-relay-editor', 'window.blackfuryRelayEditor = ' . wp_json_encode( $settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
	}

	public function article( WP_Post $post ) {
		$current = get_post_meta( $post->ID, BlackFury_Relay_Delivery::OVERRIDE, true ) ?: 'auto';
		$route = $this->relay->destination( $post );
		$card = $this->posts->preview( $post, ! blackfury_relay_post_is_embed_enabled() );
		$job = get_post_meta( $post->ID, BlackFury_Relay_Delivery::JOB, true );
		wp_nonce_field( 'blackfury_relay_article_' . $post->ID, 'blackfury_relay_article_nonce' );
		?>
		<div class="bfr-editor">
			<p><label for="bfr-article-destination"><strong><?php esc_html_e( 'Destination', 'blackfury-relay' ); ?></strong></label><br>
				<select id="bfr-article-destination" name="blackfury_relay_override">
					<option value="auto" <?php selected( $current, 'auto' ); ?>><?php esc_html_e( 'Follow category and default rules', 'blackfury-relay' ); ?></option>
					<option value="off" <?php selected( $current, 'off' ); ?>><?php esc_html_e( 'Do not notify Discord', 'blackfury-relay' ); ?></option>
					<?php $this->channel_choices( $current, BlackFury_Relay_Delivery::channels() ); ?>
				</select>
			</p>
			<p class="description"><?php esc_html_e( 'Preview uses the saved article. Save your draft to refresh the card and destination.', 'blackfury-relay' ); ?></p>
			<?php if ( 'off' !== $route['id'] ) : ?>
			<div class="bfr-preview">
				<span class="bfr-eyebrow"><?php echo esc_html( $route['label'] ); ?></span>
				<p><?php echo esc_html( html_entity_decode( $card['content'], ENT_QUOTES, 'UTF-8' ) ); ?></p>
				<?php if ( $card['embed'] ) : ?>
					<div class="bfr-embed" style="--bfr-accent:<?php echo esc_attr( sanitize_hex_color( $route['color'] ?? '' ) ?: '#5865f2' ); ?>">
						<strong><?php echo esc_html( $card['embed']['title'] ); ?></strong>
						<p><?php echo esc_html( $card['embed']['description'] ); ?></p>
						<?php if ( ! empty( $card['embed']['image'] ) ) : ?>
							<img alt="" src="<?php echo esc_url( $card['embed']['image'] ); ?>" loading="lazy">
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
			<?php if ( $job ) : ?>
				<p><?php echo esc_html( $this->status_label( $job['status'] ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	public function save_article( $id, WP_Post $post ) {
		if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ! current_user_can( 'edit_post', $id )
			|| ! wp_verify_nonce( self::input( 'blackfury_relay_article_nonce' ), 'blackfury_relay_article_' . $id ) ) {
			return;
		}
		$selection = sanitize_key( self::input( 'blackfury_relay_override' ) );
		if ( in_array( $selection, array( 'auto', 'off' ), true ) || isset( BlackFury_Relay_Delivery::channels()[ $selection ] ) ) {
			update_post_meta( $id, BlackFury_Relay_Delivery::OVERRIDE, $selection );
		}
	}

	public function manage() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage BlackFury Relay.', 'blackfury-relay' ), '', array( 'response' => 403 ) );
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( esc_html__( 'This action requires POST.', 'blackfury-relay' ), '', array( 'response' => 405 ) );
		}
		if ( ! wp_verify_nonce( self::input( '_wpnonce' ), self::ACTION ) ) {
			wp_die( esc_html__( 'Reload BlackFury Relay and try again.', 'blackfury-relay' ), '', array( 'response' => 403 ) );
		}
		$operation = sanitize_key( self::input( 'operation' ) );
		$channels = BlackFury_Relay_Delivery::channels();
		$id = sanitize_key( self::input( 'channel' ) );
		$notice = 'invalid';
		switch ( $operation ) {
			case 'add':
				$label = wp_html_excerpt( sanitize_text_field( self::input( 'label' ) ), 80 );
				$url = esc_url_raw( trim( self::input( 'url' ) ), array( 'https' ) );
				$guild = trim( self::input( 'guild' ) );
				$color = sanitize_hex_color( self::input( 'color' ) ) ?: '#5865f2';
				if ( $label && BlackFury_Relay_Delivery::is_webhook( $url ) && ( ! $guild || BlackFury_Relay_Delivery::is_id( $guild ) ) && count( $channels ) < 50 ) {
					$channels[ wp_generate_uuid4() ] = compact( 'label', 'url', 'guild', 'color' );
					$notice = update_option( BlackFury_Relay_Delivery::CHANNELS, $channels, false ) ? 'saved' : 'invalid';
				}
				break;
			case 'default':
				if ( '' === $id || isset( $channels[ $id ] ) ) {
					update_option( BlackFury_Relay_Delivery::DEFAULT_CHANNEL, $id, false );
					$notice = 'saved';
				}
				break;
			case 'rule':
				$term = absint( self::input( 'term' ) );
				if ( term_exists( $term, 'category' ) && ( '' === $id || isset( $channels[ $id ] ) ) ) {
					if ( '' === $id ) {
						delete_term_meta( $term, BlackFury_Relay_Delivery::RULE );
					} else {
						update_term_meta( $term, BlackFury_Relay_Delivery::RULE, $id );
					}
					$notice = 'saved';
				}
				break;
			case 'delete':
				if ( isset( $channels[ $id ] ) ) {
					unset( $channels[ $id ] );
					// Remember affected rules and overrides as unavailable rather than redirecting their messages.
					$notice = update_option( BlackFury_Relay_Delivery::CHANNELS, $channels, false ) ? 'saved' : 'invalid';
				}
				break;
			case 'test':
				$url = $channels[ $id ]['url'] ?? '';
				if ( 'legacy' === $id ) {
					$url = ( new BlackFury_Relay_Plugin_HTTP( 'post' ) )->get_webhook_url();
				}
				if ( $url ) {
					$response = ( new BlackFury_Relay_Plugin_HTTP( 'post' ) )->relay( $url, __( 'BlackFury Relay connection test', 'blackfury-relay' ) );
					$data = json_decode( wp_remote_retrieve_body( $response ), true );
					$code = (int) wp_remote_retrieve_response_code( $response );
					$notice = ( $code >= 200 && $code < 300 && BlackFury_Relay_Delivery::is_id( $data['id'] ?? '' ) ) ? 'test_ok' : 'test_unconfirmed';
				}
				break;
			case 'retry':
				if ( $this->relay->retry( absint( self::input( 'post_id' ) ), 'yes' === self::input( 'confirm_uncertain' ) ) ) {
					$notice = 'queued';
				}
				break;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'bfr_notice' => $notice ), admin_url( 'admin.php' ) ), 303 );
		exit;
	}

	private static function input( $key ) {
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	}

	private function start_form( $operation, $class = '' ) {
		printf( '<form method="post" action="%s" class="%s">', esc_url( admin_url( 'admin-post.php' ) ), esc_attr( $class ) );
		wp_nonce_field( self::ACTION );
		printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="operation" value="%s">', esc_attr( self::ACTION ), esc_attr( $operation ) );
	}

	private function channel_choices( $selected, array $channels ) {
		if ( $selected && ! isset( $channels[ $selected ] ) && ! in_array( $selected, array( 'auto', 'off' ), true ) ) {
			printf( '<option value="%s" selected>%s</option>', esc_attr( $selected ), esc_html__( 'Unavailable channel', 'blackfury-relay' ) );
		}
		foreach ( $channels as $key => $channel ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $selected, $key, false ), esc_html( $channel['label'] ) );
		}
	}

	public function screen() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$channels = BlackFury_Relay_Delivery::channels();
		$default = get_option( BlackFury_Relay_Delivery::DEFAULT_CHANNEL, '' );
		$categories = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
		$notice = isset( $_GET['bfr_notice'] ) && is_string( $_GET['bfr_notice'] ) ? sanitize_key( wp_unslash( $_GET['bfr_notice'] ) ) : '';
		$messages = array(
			'saved' => __( 'Settings saved.', 'blackfury-relay' ),
			'queued' => __( 'Notification queued again.', 'blackfury-relay' ),
			'test_ok' => __( 'Discord accepted the test message. Check the target channel.', 'blackfury-relay' ),
			'test_unconfirmed' => __( 'Discord did not confirm the test. Check the channel and webhook before trying again.', 'blackfury-relay' ),
			'invalid' => __( 'The action could not be completed. Check the fields or refresh the page.', 'blackfury-relay' ),
		);
		?>
		<div class="wrap bfr-wrap">
			<header class="bfr-hero"><p class="bfr-eyebrow">WORDPRESS → DISCORD</p><h1>BlackFury Relay</h1><p><?php esc_html_e( 'Choose where your articles go and track each delivery.', 'blackfury-relay' ); ?></p></header>
			<?php if ( BlackFury_Relay_Plugin::delivery_paused() ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Relay delivery is paused. Open Migration to finish switching from WP Discord Post.', 'blackfury-relay' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $messages[ $notice ] ) ) : ?>
				<div class="notice <?php echo esc_attr( in_array( $notice, array( 'invalid', 'test_unconfirmed' ), true ) ? 'notice-warning' : 'notice-success' ); ?>"><p><?php echo esc_html( $messages[ $notice ] ); ?></p></div>
			<?php endif; ?>
			<div class="bfr-grid">
				<section class="bfr-panel">
					<h2><?php esc_html_e( 'Connect a channel', 'blackfury-relay' ); ?></h2>
					<p><?php esc_html_e( 'In Discord, create a webhook in the target channel, copy its URL, then save it here and send a test.', 'blackfury-relay' ); ?></p>
					<?php $this->start_form( 'add', 'bfr-stack' ); ?>
					<label><?php esc_html_e( 'Channel label', 'blackfury-relay' ); ?><input name="label" type="text" required maxlength="80" placeholder="#gaming"></label>
					<label><?php esc_html_e( 'Discord webhook URL', 'blackfury-relay' ); ?><input name="url" type="password" autocomplete="off" required></label>
					<label><?php esc_html_e( 'Discord server ID (optional, enables message links)', 'blackfury-relay' ); ?><input name="guild" type="text" inputmode="numeric" pattern="[0-9]{17,20}"></label>
					<label><?php esc_html_e( 'Embed accent', 'blackfury-relay' ); ?> <input name="color" type="color" value="#5865f2"></label>
					<?php submit_button( __( 'Save channel', 'blackfury-relay' ), 'primary', 'submit', false ); ?>
					</form>
					<ul class="bfr-channels">
						<?php foreach ( array( 'legacy' => array( 'label' => __( 'Existing webhook', 'blackfury-relay' ) ) ) + $channels as $id => $channel ) : ?>
							<li><strong><?php echo esc_html( $channel['label'] ); ?></strong>
							<?php $this->start_form( 'test' ); ?><input type="hidden" name="channel" value="<?php echo esc_attr( $id ); ?>"><?php submit_button( __( 'Send test', 'blackfury-relay' ), 'secondary small', 'submit', false ); ?></form>
							<?php if ( 'legacy' !== $id ) : ?>
								<details><summary><?php esc_html_e( 'Remove…', 'blackfury-relay' ); ?></summary>
								<p><?php esc_html_e( 'Rules pointing here will stop delivering until reassigned.', 'blackfury-relay' ); ?></p>
								<?php $this->start_form( 'delete' ); ?><input type="hidden" name="channel" value="<?php echo esc_attr( $id ); ?>"><?php submit_button( __( 'Remove channel', 'blackfury-relay' ), 'secondary small', 'submit', false ); ?></form>
								</details>
							<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
				<section class="bfr-panel">
					<h2><?php esc_html_e( 'Article routing', 'blackfury-relay' ); ?></h2>
					<p><?php esc_html_e( 'An article override wins, then the matching category with the lowest ID, then the default channel.', 'blackfury-relay' ); ?></p>
					<?php $this->start_form( 'default', 'bfr-line' ); ?>
					<label><?php esc_html_e( 'Default channel', 'blackfury-relay' ); ?>
						<select name="channel"><option value=""><?php esc_html_e( 'Existing webhook', 'blackfury-relay' ); ?></option><?php $this->channel_choices( $default, $channels ); ?></select>
					</label><?php submit_button( __( 'Save', 'blackfury-relay' ), 'secondary', 'submit', false ); ?></form>
					<?php foreach ( is_array( $categories ) ? $categories : array() as $term ) : ?>
						<?php $this->start_form( 'rule', 'bfr-line' ); ?>
						<input type="hidden" name="term" value="<?php echo esc_attr( $term->term_id ); ?>">
						<label><?php echo esc_html( $term->name ); ?>
							<select name="channel"><option value=""><?php esc_html_e( 'No category rule', 'blackfury-relay' ); ?></option><?php $this->channel_choices( get_term_meta( $term->term_id, BlackFury_Relay_Delivery::RULE, true ), $channels ); ?></select>
						</label><?php submit_button( __( 'Save', 'blackfury-relay' ), 'secondary', 'submit', false ); ?></form>
					<?php endforeach; ?>
				</section>
			</div>
			<?php $this->journal(); ?>
		</div>
		<?php
	}

	private function journal() {
		$posts = get_posts( array(
			'post_type' => 'post',
			'post_status' => array( 'publish', 'private', 'draft', 'future', 'pending', 'trash' ),
			'numberposts' => 50,
			'meta_key' => BlackFury_Relay_Delivery::JOB,
			'orderby' => 'modified',
		) );
		?>
		<section class="bfr-panel bfr-journal">
			<h2><?php esc_html_e( 'Delivery journal', 'blackfury-relay' ); ?></h2>
			<p><?php esc_html_e( 'Last 50 tracked articles. Pending deliveries use WordPress scheduled tasks.', 'blackfury-relay' ); ?></p>
			<?php if ( ! $posts ) : ?><p><?php esc_html_e( 'No tracked articles yet.', 'blackfury-relay' ); ?></p><?php else : ?>
			<table class="widefat striped"><thead><tr>
				<th><?php esc_html_e( 'Article', 'blackfury-relay' ); ?></th><th><?php esc_html_e( 'Destination', 'blackfury-relay' ); ?></th>
				<th><?php esc_html_e( 'Status', 'blackfury-relay' ); ?></th><th><?php esc_html_e( 'Last activity', 'blackfury-relay' ); ?></th>
			</tr></thead><tbody>
			<?php foreach ( $posts as $post ) :
				$job = get_post_meta( $post->ID, BlackFury_Relay_Delivery::JOB, true );
				$status = $job['status'];
				?>
				<tr>
					<td><a href="<?php echo esc_url( get_edit_post_link( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ?: __( '(no title)', 'blackfury-relay' ) ); ?></a></td>
					<td><?php echo esc_html( $job['label'] ?? '—' ); ?></td>
					<td><span class="bfr-status bfr-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $this->status_label( $status ) ); ?></span>
						<?php if ( ! empty( $job['code'] ) ) : ?> <small>HTTP <?php echo esc_html( $job['code'] ); ?></small><?php endif; ?>
						<?php if ( ! empty( $job['reason'] ) ) : ?><p class="description"><?php echo esc_html( $this->reason_label( $job['reason'] ) ); ?></p><?php endif; ?>
						<?php if ( 'pending' === $status && ! empty( $job['next'] ) ) : ?><p class="description"><?php echo esc_html( wp_date( 'Y-m-d H:i', $job['next'] ) ); ?></p><?php endif; ?>
						<?php if ( 'accepted' === $status && ! empty( $job['message'] ) ) : ?>
							<?php if ( ! empty( $job['guild'] ) && ! empty( $job['channel'] ) ) : ?>
								<a href="<?php echo esc_url( 'https://discord.com/channels/' . $job['guild'] . '/' . $job['channel'] . '/' . $job['message'] ); ?>"><?php esc_html_e( 'Open in Discord', 'blackfury-relay' ); ?></a>
							<?php else : ?><small><?php echo esc_html( $job['message'] ); ?></small><?php endif; ?>
						<?php elseif ( ( $job['retry_not_before'] ?? 0 ) > time() ) : ?>
							<p class="description"><?php printf( esc_html__( 'Retry available after %s.', 'blackfury-relay' ), esc_html( wp_date( 'Y-m-d H:i', $job['retry_not_before'] ) ) ); ?></p>
						<?php elseif ( in_array( $status, array( 'pending', 'sending' ), true ) && ! wp_next_scheduled( BlackFury_Relay_Delivery::EVENT, array( $post->ID ) ) ) : ?>
							<?php $this->start_form( 'retry', 'bfr-retry' ); ?>
							<input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>">
							<?php submit_button( __( 'Recover scheduled task', 'blackfury-relay' ), 'secondary small', 'submit', false ); ?></form>
						<?php elseif ( in_array( $status, array( 'failed', 'uncertain' ), true ) ) : ?>
							<?php $this->start_form( 'retry', 'bfr-retry' ); ?>
							<input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>">
							<?php if ( 'uncertain' === $status ) : ?><label><input type="checkbox" name="confirm_uncertain" value="yes" required> <?php esc_html_e( 'I checked the channel. Retrying may duplicate a message.', 'blackfury-relay' ); ?></label><?php endif; ?>
							<?php submit_button( __( 'Retry with current routing', 'blackfury-relay' ), 'secondary small', 'submit', false ); ?></form>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $job['updated'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table><?php endif; ?>
		</section>
		<?php
	}

	private function status_label( $status ) {
		$labels = array(
			'pending' => __( 'Pending', 'blackfury-relay' ),
			'sending' => __( 'Sending', 'blackfury-relay' ),
			'accepted' => __( 'Accepted by Discord', 'blackfury-relay' ),
			'failed' => __( 'Failed', 'blackfury-relay' ),
			'uncertain' => __( 'Unconfirmed delivery', 'blackfury-relay' ),
			'skipped' => __( 'Skipped', 'blackfury-relay' ),
		);
		return $labels[ $status ] ?? '';
	}

	private function reason_label( $reason ) {
		$labels = array(
			'invalid_destination' => __( 'Configure an HTTPS webhook or update the article routing.', 'blackfury-relay' ),
			'destination_changed' => __( 'The destination changed while the article was queued.', 'blackfury-relay' ),
			'rejected' => __( 'Discord rejected the request. Check the HTTP status and webhook.', 'blackfury-relay' ),
			'rate_limit' => __( 'The Discord rate limit was reached. Retry after it resets.', 'blackfury-relay' ),
			'scheduler' => __( 'WordPress could not schedule delivery.', 'blackfury-relay' ),
			'storage' => __( 'WordPress could not save delivery state.', 'blackfury-relay' ),
			'unconfirmed' => __( 'Check Discord before retrying. The message may already exist.', 'blackfury-relay' ),
			'interrupted' => __( 'The delivery worker was interrupted. Check the target channel.', 'blackfury-relay' ),
			'article_opt_out' => __( 'Discord delivery was disabled for this article.', 'blackfury-relay' ),
			'not_eligible' => __( 'The article is no longer eligible or was previously sent.', 'blackfury-relay' ),
		);
		return $labels[ $reason ] ?? '';
	}
}
