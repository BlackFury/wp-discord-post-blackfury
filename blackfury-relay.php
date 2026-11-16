<?php
/**
 * WP Discord Post
 *
 * @author      Nicola Mustone
 * @license     GPL-2.0+
 *
 * Plugin Name: BlackFury Relay
 * Plugin URI:  https://github.com/BlackFury/wp-discord-post-blackfury
 * Description: Preview, route and track WordPress article notifications sent to Discord.
 * Version:     2.2.0-beta.2
 * Update URI:  https://github.com/BlackFury/wp-discord-post-blackfury
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author:      Nicola Mustone
 * Author URI:  https://nicola.blog/
 * Text Domain: blackfury-relay
 *
 * Original author retained; security maintenance: BlackFury contributors.
 *
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BLACKFURY_RELAY_FILE', __FILE__ );
require_once __DIR__ . '/includes/class-blackfury-relay-updates.php';
BlackFury_Relay_Updates::register();

require_once __DIR__ . '/includes/class-blackfury-relay-migration.php';
BlackFury_Relay_Migration::register();

/**
 * Main class of the plugin WP Discord Post. Handles the bot and the admin settings.
 */
class BlackFury_Relay_Plugin {
	public static function legacy_active() {
		$legacy = 'wp-discord-post/wp-discord-post.php';
		return in_array( $legacy, (array) get_option( 'active_plugins', array() ), true )
			|| ( is_multisite() && isset( get_site_option( 'active_sitewide_plugins', array() )[ $legacy ] ) );
	}

	public static function delivery_paused() {
		return self::legacy_active() || (
			false !== get_option( 'blackfury_relay_import_cursor' ) && ! get_option( 'blackfury_relay_import_finished' )
		);
	}

	/**
	 * The single instance of the class.
	 *
	 * @var BlackFury_Relay_Plugin
	 */
	protected static $_instance = null;

	/**
	 * The instance of BlackFury_Relay_Plugin_Post.
	 *
	 * @var BlackFury_Relay_Plugin_Post
	 */
	public $post = null;

	/**
	 * The instance of BlackFury_Relay_Plugin_CF7.
	 *
	 * @var BlackFury_Relay_Plugin_CF7
	 */
	public $cf7 = null;

	/**
	 * The instance of BlackFury_Relay_Plugin_GF.
	 *
	 * @var BlackFury_Relay_Plugin_GF
	 */
	public $gf = null;

	/**
	 * The instance of BlackFury_Relay_Plugin_Jetpack_CF.
	 *
	 * @var BlackFury_Relay_Plugin_Jetpack_CF
	 */
	public $jetpack_cf = null;

	/**
	 * The instance of BlackFury_Relay_Plugin_WooCommerce.
	 *
	 * @var BlackFury_Relay_Plugin_WooCommerce
	 */
	public $woocommerce = null;

	/**
	 * Main BlackFury_Relay_Plugin Instance.
	 *
	 * Ensures only one instance of BlackFury_Relay_Plugin is loaded or can be loaded.
	 *
	 * @static
	 * @return BlackFury_Relay_Plugin - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'blackfury-relay' ), '1.0.9' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'blackfury-relay' ), '1.0.9' );
	}

	/**
	 * Adds the required hooks.
	 */
	public function __construct() {
		require_once( __DIR__ . '/includes/functions-general.php' );
		require_once( __DIR__ . '/includes/class-wp-discord-post-admin.php' );
		require_once( __DIR__ . '/includes/class-wp-discord-post-http.php' );
		require_once( __DIR__ . '/includes/class-wp-discord-post-formatting.php' );
		require_once( __DIR__ . '/includes/class-blackfury-relay.php' );
		require_once( __DIR__ . '/includes/class-blackfury-relay-admin.php' );

		if ( is_admin() ) {
			require_once( __DIR__ . '/includes/class-wp-discord-post-dank-meme.php' );
		}

		$this->post = require_once( __DIR__ . '/includes/class-wp-discord-post-post.php' );

		if ( 'yes' === get_option( 'blackfury_relay_enabled_for_cf7' ) && class_exists( 'WPCF7' ) ) {
			$this->cf7 = include_once( __DIR__ . '/includes/class-wp-discord-post-contact-form-7.php' );
		}

		if ( 'yes' === get_option( 'blackfury_relay_enabled_for_jetpack_cf' ) && class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'contact-form' ) ) {
			$this->jetpack_cf = include_once( __DIR__ . '/includes/class-wp-discord-post-jetpack-contact-form.php' );
		}

		if ( 'yes' === get_option( 'blackfury_relay_enabled_for_gf' ) && class_exists( 'GFForms' ) ) {
			$this->gf = include_once( __DIR__ . '/includes/class-wp-discord-post-gravityforms.php' );
		}

		if ( class_exists( 'WooCommerce' ) ) {
			$this->woocommerce = include_once( __DIR__ . '/includes/class-wp-discord-post-woocommerce.php' );
		}

		$this->load_textdomain();

		do_action( 'blackfury_relay_post_init' );
	}

	/**
	 * Loads the plugin localization files.
	 */
	public function load_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'blackfury-relay' );
		load_textdomain( 'blackfury-relay', WP_LANG_DIR . '/blackfury-relay/blackfury-relay-' . $locale . '.mo' );
		load_plugin_textdomain( 'blackfury-relay', false, plugin_basename( __DIR__ ) . '/languages' );
	}
}

BlackFury_Relay_Plugin::instance();
