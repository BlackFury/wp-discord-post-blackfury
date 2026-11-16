<?php
/**
 * WP Discord Post Dank Meme
 *
 * @author      Nicola Mustone
 * @license     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class to handle posts.
 */
class BlackFury_Relay_Plugin_Dank_Meme {
	public function __construct() {
		add_action( 'admin_post_blackfury_relay_post_send_dank_meme', array( $this, 'send' ) );
	}

	/**
	 * Sends a dank meme to the  main channel.
	 */
	public function send() {
		if ( BlackFury_Relay_Plugin::delivery_paused() ) {
			wp_die( esc_html__( 'Relay delivery is paused. Check the migration screen.', 'blackfury-relay' ), '', array( 'response' => 409 ) );
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_die( esc_html__( 'This action requires POST.', 'blackfury-relay' ), '', array( 'response' => 405 ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'blackfury-relay' ), '', array( 'response' => 403 ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! is_string( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'blackfury_relay_post_send_dank_meme' ) ) {
			wp_die( esc_html__( 'Invalid or expired security token. Reload the settings page.', 'blackfury-relay' ), '', array( 'response' => 403 ) );
		}

		$api_key = get_option( 'blackfury_relay_post_giphy_api_key' );
		$args    = array(
			'api_key' => $api_key,
			'tag'     => apply_filters( 'blackfury_relay_post_meme_rating', 'memes' ),
			'rating'  => apply_filters( 'blackfury_relay_post_meme_rating', 'r' ),
		);

		$url      = 'https://api.giphy.com/v1/gifs/random?' . build_query( $args );
		$response = wp_remote_get( esc_url_raw( $url ) );

		if ( ! is_wp_error( $response ) ) {
			$response = json_decode( wp_remote_retrieve_body( $response ) );
			if ( ! empty( $response->data->embed_url ) ) {
				$http = new BlackFury_Relay_Plugin_HTTP( 'giphy' );
				$http->process( $response->data->embed_url );
			}
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=blackfury-relay-advanced' ), 303 ); exit;
	}
}

new BlackFury_Relay_Plugin_Dank_Meme();
