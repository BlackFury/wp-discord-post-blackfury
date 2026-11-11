<?php
$root = getenv( 'WP_TEST_ROOT' );
if ( ! $root || ! is_file( $root . '/.blackfury-test-only' ) ) {
    throw new RuntimeException( 'Use only the disposable WordPress created for these tests.' );
}
define( 'WP_ADMIN', true );
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/wp-admin/options-general.php?page=wp-discord-post';
$_SERVER['REQUEST_METHOD'] = 'GET';
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
// Core update checks are outside the plugin and would pollute request assertions.
remove_action( 'admin_init', '_maybe_update_core' );
remove_action( 'admin_init', '_maybe_update_plugins' );
remove_action( 'admin_init', '_maybe_update_themes' );

class TestStop extends RuntimeException {
    public $status;
    public function __construct( $status ) { $this->status = $status; }
}
class TestRedirect extends RuntimeException {
    public $url;
    public $status;
    public function __construct( $url, $status ) { $this->url = $url; $this->status = $status; }
}
function expect_true( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}
add_filter( 'wp_die_handler', function () {
    return function ( $message, $title = '', $args = array() ) {
        throw new TestStop( isset( $args['response'] ) ? $args['response'] : 500 );
    };
} );
add_filter( 'wp_redirect', function ( $url, $status ) { throw new TestRedirect( $url, $status ); }, 10, 2 );
$GLOBALS['requests'] = array();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
    $GLOBALS['requests'][] = array( 'url' => $url, 'args' => $args );
    if ( false !== strpos( $url, 'api.giphy.com/' ) ) {
        return array( 'headers' => array(), 'body' => '{"data":{"embed_url":"https://giphy.com/embed/synthetic"}}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
    }
    if ( 'https://example.invalid/synthetic-webhook' === $url ) {
        return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 204, 'message' => 'No Content' ), 'cookies' => array() );
    }
    return new WP_Error( 'test_network_blocked', 'All real network requests are blocked in tests.' );
}, 10, 3 );

// All of these are synthetic fixtures, never account credentials.
$GLOBALS['fixture_options'] = array(
    'wp_discord_post_bot_username' => 'Synthetic BlackFury',
    'wp_discord_post_avatar_url' => 'https://example.invalid/avatar.png',
    'wp_discord_post_bot_token' => 'synthetic-test-token',
    'wp_discord_post_webhook_url' => 'https://example.invalid/synthetic-webhook',
    'wp_discord_post_post_webhook_url' => 'https://example.invalid/post-fixture',
    'wp_discord_post_message_format' => '%title% — %author% — %url%',
    'wp_discord_post_giphy_api_key' => 'synthetic-test-key',
    'wp_discord_post_logging' => '',
    'wp_discord_post_mention_everyone' => '',
    'wp_discord_post_disable_embed' => 'yes',
    'wp_discord_enabled_for_cf7' => 'yes',
    'wp_discord_enabled_for_jetpack_cf' => 'yes',
    'wp_discord_enabled_for_gf' => 'yes',
    'wp_discord_enabled_for_woocommerce' => 'yes',
    'wp_discord_enabled_for_woocommerce_products' => 'yes',
    'wp_discord_post_cf7_webhook_url' => 'https://example.invalid/cf7',
    'wp_discord_post_jetpack_webhook_url' => 'https://example.invalid/jetpack',
    'wp_discord_post_gf_webhook_url' => 'https://example.invalid/gf',
    'wp_discord_post_product_webhook_url' => 'https://example.invalid/products',
    'wp_discord_post_order_webhook_url' => 'https://example.invalid/orders',
    'wp_discord_product_message_format' => '%title% %price%',
    'wp_discord_order_message_format' => '%order_number% %order_total%',
);
foreach ( $GLOBALS['fixture_options'] as $key => $value ) { update_option( $key, $value ); }
$GLOBALS['admin_id'] = get_user_by( 'login', 'test-admin' )->ID;
$subscriber = get_user_by( 'login', 'test-subscriber' );
$GLOBALS['subscriber_id'] = $subscriber ? $subscriber->ID : wp_insert_user( array( 'user_login' => 'test-subscriber', 'user_pass' => bin2hex( random_bytes( 24 ) ), 'role' => 'subscriber' ) );
wp_set_current_user( $GLOBALS['admin_id'] );

function load_subject() {
    $plugin = getenv( 'WPDISCORD_PLUGIN_DIR' ) ?: dirname( __DIR__ );
    require $plugin . '/wp-discord-post.php';
}
function action_callback() {
    global $wp_filter;
    $hook = 'admin_post_wp_discord_post_send_dank_meme';
    if ( isset( $wp_filter[ $hook ] ) ) {
        foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
            foreach ( $callbacks as $callback ) { return $callback['function']; }
        }
    }
    // Also exercise the original method so RED proves its missing protections.
    return array( new WP_Discord_Post_Dank_Meme(), 'send' );
}
