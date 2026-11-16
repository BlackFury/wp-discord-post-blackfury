<?php
// Run only in the disposable installation containing the tested release ZIP.
define( 'FS_METHOD', 'direct' );
require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
try {
    $fixture = $argv[1] ?? '';
    expect_true( is_file( $fixture ), 'Prepare the update fixture first.' );
    $basename = 'blackfury-relay/blackfury-relay.php';
    $installed = WP_PLUGIN_DIR . '/' . $basename;
    expect_true( is_file( $installed ), 'Install the current package in the test site first.' );
    require $installed;
    $before = get_plugin_data( $installed, false, false )['Version'];
    $source = BlackFury_Relay_Updates::REPOSITORY . '/releases/download/v2.4.99/blackfury-relay-2.4.99.zip';
    $release = array(
        'version' => '2.4.99', 'url' => BlackFury_Relay_Updates::REPOSITORY,
        'package' => $source, 'sha256' => hash_file( 'sha256', $fixture ),
    );
    set_site_transient( BlackFury_Relay_Updates::CACHE, array( 'release' => $release ) );
    $offered = BlackFury_Relay_Updates::offer( false, get_plugin_data( $installed, false, false ), $basename );
    expect_true( '2.4.99' === $offered['version'], 'WordPress did not receive the fake stable update.' );
    set_site_transient( 'update_plugins', (object) array( 'response' => array( $basename => (object) $offered ) ) );
    $corrupt = true;
    add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$corrupt, $fixture, $source ) {
        if ( $url !== $source || empty( $args['filename'] ) ) {
            return $pre;
        }
        if ( $corrupt ) {
            file_put_contents( $args['filename'], 'corrupted update fixture' );
        } else {
            copy( $fixture, $args['filename'] );
        }
        return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
    }, 100, 3 );
    $result = ( new Plugin_Upgrader( new Automatic_Upgrader_Skin() ) )->bulk_upgrade( array( $basename ) );
    expect_true( is_wp_error( $result[ $basename ] ?? null ) && 'relay_update_checksum' === $result[ $basename ]->get_error_code(), 'Native upgrader did not reject the corrupted package.' );
    expect_true( $before === get_plugin_data( $installed, false, false )['Version'], 'Failed upgrade replaced the installed plugin.' );
    $corrupt = false;
    set_site_transient( 'update_plugins', (object) array( 'response' => array( $basename => (object) $offered ) ) );
    $result = ( new Plugin_Upgrader( new Automatic_Upgrader_Skin() ) )->bulk_upgrade( array( $basename ) );
    expect_true( ! empty( $result[ $basename ] ) && ! is_wp_error( $result[ $basename ] ), 'Native upgrader did not install the verified package.' );
    expect_true( '2.4.99' === get_plugin_data( $installed, false, false )['Version'], 'Expected upgrade did not replace Relay in its own folder.' );
    expect_true( $GLOBALS['fixture_options']['blackfury_relay_post_webhook_url'] === get_option( 'blackfury_relay_post_webhook_url' ), 'Upgrade lost the Relay webhook setting.' );
    delete_site_transient( BlackFury_Relay_Updates::CACHE );
    echo "PASS WordPress update: rejected corrupt ZIP, installed verified stable ZIP, preserved settings\n";
} catch ( Throwable $e ) {
    fwrite( STDERR, 'FAIL WordPress update: ' . $e->getMessage() . "\n" );
    exit( 1 );
}
