<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Temporary, same-site import. Remove this module and its registration for 2.5. */
final class BlackFury_Relay_Migration {
	const CURSOR = 'blackfury_relay_import_cursor';
	const FINISHED = 'blackfury_relay_import_finished';
	const PAGE = 'blackfury-relay-migration';
	const ACTION = 'blackfury_relay_import_origin';
	const BATCH = 250;
	private const KEYS = array(
		'post_avatar_url', 'post_bot_token', 'post_bot_username', 'post_webhook_url',
		'post_post_webhook_url', 'post_message_format', 'post_logging', 'post_mention_everyone',
		'post_disable_embed', 'post_giphy_api_key', 'post_giphy_webhook_url', 'post_gf_settings',
		'enabled_for_cf7', 'enabled_for_gf', 'enabled_for_jetpack_cf', 'enabled_for_woocommerce',
		'enabled_for_woocommerce_products', 'post_cf7_webhook_url', 'post_gf_webhook_url',
		'post_jetpack_webhook_url', 'post_order_webhook_url', 'post_product_webhook_url',
		'order_message_format', 'product_message_format',
	);

	public static function register() {
		add_action( 'admin_menu', array( self::class, 'menu' ), 20 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	public static function menu() {
		add_submenu_page( 'blackfury-relay', __( 'Migration', 'blackfury-relay' ), __( 'Migration', 'blackfury-relay' ), 'manage_options', self::PAGE, array( self::class, 'render' ) );
	}

	public static function import_batch() {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) || BlackFury_Relay_Plugin::legacy_active() ) {
			return new WP_Error( 'relay_import_blocked' );
		}
		if ( get_option( self::FINISHED ) ) {
			return true;
		}
		add_option( self::CURSOR, 0, '', false );
		$missing = new stdClass();
		foreach ( self::KEYS as $key ) {
			$old = get_option( 'wp_discord_' . $key, $missing );
			$new = 'blackfury_relay_' . $key;
			if ( $missing !== $old && $missing === get_option( $new, $missing )
				&& ! add_option( $new, $old, '', false ) && $missing === get_option( $new, $missing ) ) {
				return new WP_Error( 'relay_import_storage' );
			}
		}
		$cursor = (int) get_option( self::CURSOR, 0 );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_id, post_id FROM {$wpdb->postmeta} WHERE meta_id > %d AND meta_key = %s AND meta_value = %s ORDER BY meta_id ASC LIMIT %d",
			$cursor, '_wp_discord_post_published', 'yes', self::BATCH + 1
		), ARRAY_A );
		if ( ! is_array( $rows ) || $wpdb->last_error ) {
			return new WP_Error( 'relay_import_storage' );
		}
		foreach ( array_slice( $rows, 0, self::BATCH ) as $row ) {
			$id = (int) $row['post_id'];
			$key = '_blackfury_relay_published';
			if ( ! metadata_exists( 'post', $id, $key ) && ! add_post_meta( $id, $key, 'yes', true ) && ! metadata_exists( 'post', $id, $key ) ) {
				return new WP_Error( 'relay_import_storage' );
			}
			$cursor = (int) $row['meta_id'];
		}
		if ( count( $rows ) > self::BATCH ) {
			update_option( self::CURSOR, $cursor, false );
			return (int) get_option( self::CURSOR ) >= $cursor ? false : new WP_Error( 'relay_import_storage' );
		}
		update_option( self::FINISHED, 'yes', false );
		if ( ! get_option( self::FINISHED ) ) {
			return new WP_Error( 'relay_import_storage' );
		}
		delete_option( self::CURSOR );
		return true;
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( esc_html__( 'This action requires an administrative POST.', 'blackfury-relay' ), '', array( 'response' => 403 ) );
		}
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			wp_die( esc_html__( 'Reload the migration page and try again.', 'blackfury-relay' ), '', array( 'response' => 403 ) );
		}
		$result = self::import_batch();
		$status = is_wp_error( $result ) ? 'error' : ( $result ? 'done' : 'more' );
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'relay_import' => $status ), admin_url( 'admin.php' ) ), 303 );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$active = BlackFury_Relay_Plugin::legacy_active();
		$done = get_option( self::FINISHED );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Migrate from WP Discord Post', 'blackfury-relay' ); ?></h1>
			<p><?php esc_html_e( 'Copies existing settings and sent markers on this site. Existing Relay settings take precedence. Back up the database before switching plugins.', 'blackfury-relay' ); ?></p>
			<p><?php esc_html_e( 'This temporary importer will be removed in version 2.5. Finish the migration before upgrading to 2.5.', 'blackfury-relay' ); ?></p>
			<?php if ( $active ) : ?>
				<p><?php esc_html_e( 'Deactivate WP Discord Post without deleting it, then reload this page. Relay delivery is paused while it is active.', 'blackfury-relay' ); ?></p>
			<?php elseif ( $done ) : ?>
				<p><?php esc_html_e( 'Migration complete. Test your Discord channel before resuming publication.', 'blackfury-relay' ); ?></p>
			<?php else : ?>
				<?php if ( 'error' === ( $_GET['relay_import'] ?? '' ) ) : ?>
					<p><?php esc_html_e( 'The last attempt could not finish. Check the database and retry the batch.', 'blackfury-relay' ); ?></p>
				<?php endif; ?>
				<?php if ( false !== get_option( self::CURSOR ) ) : ?>
					<p><?php esc_html_e( 'An import is in progress. Continue until it is complete. Relay delivery is paused in the meantime.', 'blackfury-relay' ); ?></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>
					<?php submit_button( __( 'Import or continue', 'blackfury-relay' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
