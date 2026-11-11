<?php
// For a freshly created, disposable WordPress only. Never use a site database.
$root = getenv( 'WP_TEST_ROOT' );
if ( ! $root || ! is_file( $root . '/.blackfury-test-only' ) ) {
    fwrite( STDERR, "Refusing a WordPress without the disposable-test marker.\n" );
    exit( 2 );
}
define( 'WP_INSTALLING', true );
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if ( ! is_blog_installed() ) {
    wp_install( 'Disposable BlackFury tests', 'test-admin', 'admin@example.invalid', false, '', bin2hex( random_bytes( 24 ) ) );
}
echo "Disposable WordPress ready.\n";
