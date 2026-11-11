<?php
// Run after extracting the built ZIP into the disposable WordPress plugins folder.
require __DIR__ . '/bootstrap.php';
try {
    $plugin = 'wp-discord-post/wp-discord-post.php';
    expect_true( is_file( WP_PLUGIN_DIR . '/' . $plugin ), 'Extract the built ZIP into the disposable plugins directory first.' );
    $metadata = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
    expect_true( '2.1.1' === $metadata['Version'], 'Wrong installed version.' );
    expect_true( 'false' === $metadata['UpdateURI'], 'WordPress.org update exclusion missing.' );
    $id = wp_insert_post( array( 'post_title' => 'Synthetic pre-existing marker', 'post_status' => 'draft' ) );
    add_post_meta( $id, '_wp_discord_post_published', 'yes' );
    $capture = function () {
        ob_start();
        require dirname( __DIR__ ) . '/scripts/settings-fingerprint.php';
        return ob_get_clean();
    };
    $before = $capture();
    for ( $i = 0; $i < 2; ++$i ) {
        $result = activate_plugin( $plugin );
        expect_true( ! is_wp_error( $result ), 'Activation failed: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
        expect_true( is_plugin_active( $plugin ), 'Wrong plugin basename after activation.' );
        expect_true( $before === $capture(), 'Activation changed options or publication markers.' );
        deactivate_plugins( $plugin, true );
        expect_true( $before === $capture(), 'Deactivation changed options or publication markers.' );
    }
    expect_true( 0 === count( $GLOBALS['requests'] ), 'Lifecycle triggered an external request.' );
    echo "PASS installed ZIP: WordPress metadata, activation/reactivation, settings and publication markers preserved\n";
} catch ( Throwable $e ) {
    fwrite( STDERR, 'FAIL installed ZIP: ' . $e->getMessage() . "\n" );
    exit( 1 );
}
