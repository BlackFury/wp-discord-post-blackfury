<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A stable-channel provider for WordPress's native plugin updater. */
final class BlackFury_Relay_Updates {
	const PLUGIN = 'blackfury-relay/blackfury-relay.php';
	const REPOSITORY = 'https://github.com/BlackFury/wp-discord-post-blackfury';
	const API = 'https://api.github.com/repos/BlackFury/wp-discord-post-blackfury/releases/latest';
	const CACHE = 'blackfury_relay_stable_release';

	public static function register() {
		add_filter( 'update_plugins_github.com', array( self::class, 'offer' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( self::class, 'verify_download' ), 99, 4 );
		add_filter( 'upgrader_source_selection', array( self::class, 'verify_layout' ), 99, 4 );
	}

	public static function parse_release( $release ) {
		if ( ! is_array( $release ) || false !== ( $release['draft'] ?? null ) || false !== ( $release['prerelease'] ?? null )
			|| ! is_string( $release['tag_name'] ?? null ) || ! preg_match( '/^v([0-9]+\.[0-9]+\.[0-9]+)$/D', $release['tag_name'], $version )
			|| ! is_array( $release['assets'] ?? null ) ) {
			return false;
		}
		$name = 'blackfury-relay-' . $version[1] . '.zip';
		$package = self::REPOSITORY . '/releases/download/' . $release['tag_name'] . '/' . $name;
		foreach ( $release['assets'] as $asset ) {
			if ( $name === ( $asset['name'] ?? null ) && $package === ( $asset['browser_download_url'] ?? null )
				&& is_string( $asset['digest'] ?? null ) && preg_match( '/^sha256:([a-f0-9]{64})$/iD', $asset['digest'], $sha ) ) {
				return array(
					'version' => $version[1],
					'package' => $package,
					'url' => self::REPOSITORY . '/releases/tag/' . $release['tag_name'],
					'sha256' => strtolower( $sha[1] ),
				);
			}
		}
		return false;
	}

	private static function release() {
		$cached = get_site_transient( self::CACHE );
		if ( is_array( $cached ) && array_key_exists( 'release', $cached ) ) {
			return $cached['release'];
		}
		$response = wp_safe_remote_get( self::API, array(
			'timeout' => 8,
			'redirection' => 0,
			'limit_response_size' => 262144,
			'headers' => array( 'Accept' => 'application/vnd.github+json' ),
		) );
		$release = 200 === wp_remote_retrieve_response_code( $response )
			? self::parse_release( json_decode( wp_remote_retrieve_body( $response ), true ) ) : false;
		set_site_transient( self::CACHE, array( 'release' => $release ), $release ? 3 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
		return $release;
	}

	public static function offer( $update, $plugin, $file ) {
		if ( self::PLUGIN !== $file || self::REPOSITORY !== ( $plugin['UpdateURI'] ?? '' ) ) {
			return $update;
		}
		$release = self::release();
		$available = $release && version_compare( $release['version'], $plugin['Version'], '>' )
			&& ! self::import_blocks_update( $release['version'] );
		return array(
			'id' => self::REPOSITORY,
			'slug' => 'blackfury-relay',
			'plugin' => self::PLUGIN,
			// Core needs a no_update record to show the auto-updates toggle on current installs.
			'version' => $available ? $release['version'] : $plugin['Version'],
			'url' => $available ? $release['url'] : self::REPOSITORY . '/releases',
			'package' => $available ? $release['package'] : '',
		);
	}

	private static function import_blocks_update( $version ) {
		if ( version_compare( $version, '2.5.0', '<' ) ) {
			return false;
		}
		// Refuse removal of the importer in the middle of this site's migration.
		return false !== get_option( 'blackfury_relay_import_cursor' ) && ! get_option( 'blackfury_relay_import_finished' );
	}

	public static function verify_download( $reply, $package, $upgrader, $context ) {
		// Bulk and background upgrades supply the plugin basename but may omit action/type.
		if ( self::PLUGIN !== ( $context['plugin'] ?? '' ) || is_wp_error( $reply ) ) {
			return $reply;
		}
		$release = self::release();
		if ( ! $release || $package !== $release['package'] || self::import_blocks_update( $release['version'] ) ) {
			return new WP_Error( 'relay_update_unverified', __( 'Refresh the WordPress update list and try again. Relay could not verify this package.', 'blackfury-relay' ) );
		}
		$file = false === $reply ? download_url( $package, 60 ) : $reply;
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$hash = is_string( $file ) && is_readable( $file ) ? hash_file( 'sha256', $file ) : false;
		if ( ! $hash || ! hash_equals( $release['sha256'], $hash ) ) {
			if ( false === $reply && is_string( $file ) ) {
				wp_delete_file( $file );
			}
			return new WP_Error( 'relay_update_checksum', __( 'The BlackFury Relay package does not match its GitHub checksum.', 'blackfury-relay' ) );
		}
		return $file;
	}

	public static function verify_layout( $source, $remote_source, $upgrader, $context ) {
		if ( self::PLUGIN !== ( $context['plugin'] ?? '' ) || is_wp_error( $source ) ) {
			return $source;
		}
		global $wp_filesystem;
		$headers = $wp_filesystem->get_contents( trailingslashit( $source ) . basename( self::PLUGIN ) );
		$release = self::release();
		if ( $release && dirname( self::PLUGIN ) === basename( untrailingslashit( $source ) )
			&& is_string( $headers )
			&& preg_match( '/^[ \t\/*#@]*Version:[ \t]*([^\s]+)/mi', substr( $headers, 0, 8192 ), $version )
			&& preg_match( '/^[ \t\/*#@]*Update URI:[ \t]*([^\s]+)/mi', substr( $headers, 0, 8192 ), $uri )
			&& $release['version'] === $version[1] && self::REPOSITORY === $uri[1] ) {
			return $source;
		}
		return new WP_Error( 'relay_update_layout', __( 'The update ZIP does not contain the expected Relay version and plugin folder.', 'blackfury-relay' ) );
	}
}
