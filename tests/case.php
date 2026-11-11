<?php
require __DIR__ . '/bootstrap.php';
$case = $argv[1];
try {
    if ( 'render' === $case ) {
        load_subject();
        $admin = new WP_Discord_Post_Admin();
        $uris = array(
            '/wp-admin/options-general.php/" data-bf-probe="marker"?page=wp-discord-post',
            '/wp-admin/options-general.php?page=wp-discord-post&probe=%22%3E%3Cbf-probe%3E',
        );
        $admin->settings_init();
        foreach ( $uris as $uri ) {
            $_SERVER['REQUEST_URI'] = $uri;
            ob_start();
            $admin->settings_page_html();
            $html = ob_get_clean();
            $doc = new DOMDocument();
            @$doc->loadHTML( $html );
            $xpath = new DOMXPath( $doc );
            expect_true( 0 === $xpath->query( '//*[@data-bf-probe] | //bf-probe' )->length, 'Request URI altered the HTML structure.' );
            $forms = $xpath->query( '//form' );
            expect_true( 2 === $forms->length, 'Expected separate settings and send forms.' );
            $send = $xpath->query( '//form[@id="wp-discord-post-dank-meme-form"]' )->item( 0 );
            expect_true( $send && 'post' === strtolower( $send->getAttribute( 'method' ) ), 'Send form must use POST.' );
            expect_true( false === strpos( $doc->saveHTML( $send ), 'bf-probe' ), 'Request URI leaked into the send form.' );
            expect_true( admin_url( 'admin-post.php' ) === $send->getAttribute( 'action' ), 'Send URL must be fixed.' );
            $nonce = $xpath->query( './/input[@name="_wpnonce"]', $send )->item( 0 );
            expect_true( $nonce && wp_verify_nonce( $nonce->getAttribute( 'value' ), 'wp_discord_post_send_dank_meme' ), 'Send form requires a valid specific nonce.' );
            expect_true( 1 === $xpath->query( '//button[@form="wp-discord-post-dank-meme-form"]' )->length, 'Button must submit its independent form.' );
        }
    } elseif ( 'legacy-get' === $case ) {
        $_GET['dank_meme'] = 'yes';
        $_REQUEST = $_GET;
        load_subject();
        try { do_action( 'admin_init' ); } catch ( TestRedirect $e ) {}
        expect_true( 0 === count( $GLOBALS['requests'] ), 'Legacy GET triggered outbound requests.' );
    } elseif ( in_array( $case, array( 'get', 'missing-nonce', 'bad-nonce', 'array-nonce', 'wrong-action-nonce', 'expired-nonce', 'subscriber', 'anonymous', 'query-only-nonce', 'valid-post' ), true ) ) {
        load_subject();
        $_SERVER['REQUEST_METHOD'] = 'get' === $case ? 'GET' : 'POST';
        if ( 'subscriber' === $case ) { wp_set_current_user( $GLOBALS['subscriber_id'] ); }
        if ( 'anonymous' === $case ) { wp_set_current_user( 0 ); }
        $_POST = array( 'action' => 'wp_discord_post_send_dank_meme', '_wpnonce' => wp_create_nonce( 'wp_discord_post_send_dank_meme' ) );
        if ( 'missing-nonce' === $case ) { unset( $_POST['_wpnonce'] ); }
        if ( 'bad-nonce' === $case ) { $_POST['_wpnonce'] = 'invalid'; }
        if ( 'array-nonce' === $case ) { $_POST['_wpnonce'] = array( 'invalid' ); }
        if ( 'wrong-action-nonce' === $case ) { $_POST['_wpnonce'] = wp_create_nonce( 'unrelated-action' ); }
        if ( 'expired-nonce' === $case ) {
            $_POST['_wpnonce'] = substr( wp_hash( ( wp_nonce_tick() - 3 ) . '|wp_discord_post_send_dank_meme|' . get_current_user_id() . '|' . wp_get_session_token(), 'nonce' ), -12, 10 );
        }
        if ( 'query-only-nonce' === $case ) { $_GET['_wpnonce'] = $_POST['_wpnonce']; unset( $_POST['_wpnonce'] ); }
        $_REQUEST = array_merge( $_GET, $_POST );
        $status = null;
        try { call_user_func( action_callback() ); } catch ( TestStop $e ) { $status = $e->status; } catch ( TestRedirect $e ) {
            $status = $e->status;
            if ( 'valid-post' === $case ) { expect_true( admin_url( 'options-general.php?page=wp-discord-post' ) === $e->url, 'Unexpected redirect destination.' ); }
        }
        if ( 'valid-post' === $case ) {
            expect_true( 303 === $status, 'Successful POST must redirect with 303.' );
            expect_true( 2 === count( $GLOBALS['requests'] ), 'Authorized send must perform exactly two HTTP requests.' );
            expect_true( 0 === strpos( $GLOBALS['requests'][0]['url'], 'https://api.giphy.com/' ), 'Giphy must use HTTPS.' );
            $payload = json_decode( $GLOBALS['requests'][1]['args']['body'], true );
            expect_true( 'https://giphy.com/embed/synthetic' === $payload['content'], 'Meme content was not forwarded.' );
        } else {
            expect_true( 0 === count( $GLOBALS['requests'] ), 'Rejected request triggered network activity: ' . $case );
            expect_true( ( 'get' === $case ? 405 : 403 ) === $status, 'Wrong rejection status: ' . $case );
        }
    } elseif ( 'settings' === $case ) {
        load_subject();
        do_action( 'admin_init' );
        foreach ( $GLOBALS['fixture_options'] as $key => $value ) {
            expect_true( $value === get_option( $key ), 'Setting changed on load: ' . $key );
        }
        $admin = new WP_Discord_Post_Admin();
        update_option( 'wp_discord_post_bot_username', '"><bf-probe>marker</bf-probe>' );
        ob_start(); $admin->print_bot_username_field(); $html = ob_get_clean();
        expect_true( false === strpos( $html, '<bf-probe>' ), 'Stored setting escaped its field.' );
        wp_set_current_user( $GLOBALS['subscriber_id'] );
        ob_start(); $admin->settings_page_html(); $html = ob_get_clean();
        expect_true( '' === $html, 'Subscriber can render settings.' );
    } elseif ( 'publish-once' === $case ) {
        load_subject();
        $id = wp_insert_post( array( 'post_title' => 'Synthetic publish probe', 'post_content' => 'Synthetic only', 'post_author' => $GLOBALS['admin_id'], 'post_status' => 'draft' ) );
        $GLOBALS['requests'] = array();
        wp_publish_post( $id );
        expect_true( 1 === count( $GLOBALS['requests'] ), 'Publication must send once.' );
        expect_true( 'yes' === get_post_meta( $id, '_wp_discord_post_published', true ), 'Missing publication marker.' );
        $payload = json_decode( $GLOBALS['requests'][0]['args']['body'], true );
        expect_true( false !== strpos( $payload['content'], 'Synthetic publish probe' ), 'Published title missing.' );
        wp_update_post( array( 'ID' => $id, 'post_title' => 'Synthetic edited probe' ) );
        expect_true( 1 === count( $GLOBALS['requests'] ), 'Updating an article sent a duplicate.' );
    } elseif ( 'routing' === $case ) {
        load_subject();
        expect_true( has_action( 'admin_post_wp_discord_post_send_dank_meme' ), 'Authenticated send route is missing.' );
        expect_true( ! has_action( 'admin_post_nopriv_wp_discord_post_send_dank_meme' ), 'Anonymous send route must not exist.' );
    } elseif ( 'settings-save' === $case ) {
        load_subject();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['PHP_SELF'] = '/wp-admin/options.php';
        $GLOBALS['pagenow'] = 'options.php';
        $_COOKIE[AUTH_COOKIE] = wp_generate_auth_cookie( $GLOBALS['admin_id'], time() + 3600, 'auth' );
        $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $GLOBALS['admin_id'], time() + 3600, 'logged_in' );
        $_POST = $GLOBALS['fixture_options'];
        $_POST['wp_discord_post_bot_username'] = 'Edited synthetic username';
        $_POST['action'] = 'update';
        $_POST['option_page'] = 'wp-discord-post';
        $_POST['_wpnonce'] = wp_create_nonce( 'wp-discord-post-options' );
        $_POST['_wp_http_referer'] = '/wp-admin/options-general.php?page=wp-discord-post';
        $_REQUEST = $_POST;
        $redirected = false;
        try { require ABSPATH . 'wp-admin/options.php'; } catch ( TestRedirect $e ) {
            $redirected = false !== strpos( $e->url, 'settings-updated=true' );
        }
        expect_true( $redirected, 'Settings save did not complete.' );
        expect_true( 'Edited synthetic username' === get_option( 'wp_discord_post_bot_username' ), 'Settings form did not persist the edited value.' );
        foreach ( $GLOBALS['fixture_options'] as $key => $value ) {
            if ( 'wp_discord_post_bot_username' !== $key ) { expect_true( $value === get_option( $key ), 'Saving settings changed another option: ' . $key ); }
        }
        expect_true( 0 === count( $GLOBALS['requests'] ), 'Saving settings triggered a notification.' );
    } elseif ( 'fingerprint' === $case ) {
        $script = dirname( __DIR__ ) . '/scripts/settings-fingerprint.php';
        // WP-CLI evaluates files inside a function scope, not the global scope.
        $capture = function () use ( $script ) { ob_start(); require $script; return ob_get_clean(); };
        $before = $capture();
        expect_true( false === strpos( $before, 'synthetic-test-token' ), 'Fingerprint exposed a secret.' );
        load_subject();
        do_action( 'admin_init' );
        $after = $capture();
        expect_true( $before === $after, 'Plugin load changed the settings fingerprint.' );
        update_option( 'wp_discord_enabled_for_gf', 'no' );
        $changed = $capture();
        expect_true( $before !== $changed, 'Fingerprint missed an integration flag change.' );
    } elseif ( 'log-redaction' === $case ) {
        load_subject();
        update_option( 'wp_discord_post_logging', 'yes' );
        $log = tempnam( sys_get_temp_dir(), 'bf-test-' );
        ini_set( 'error_log', $log );
        try {
            $http = new WP_Discord_Post_HTTP();
            $http->process( 'synthetic-private-message', array( 'description' => 'synthetic-private-embed' ) );
            add_filter( 'pre_http_request', function () { return new WP_Error( 'fixture_failure', 'synthetic-private-error' ); }, 100 );
            $http->process( 'synthetic-private-message' );
            $contents = file_get_contents( $log );
            foreach ( array( 'synthetic-test-token', 'synthetic-private-message', 'synthetic-private-embed', 'synthetic-private-error' ) as $secret ) {
                expect_true( false === strpos( $contents, $secret ), 'Debug logging exposed private request data.' );
            }
            expect_true( false !== strpos( $contents, 'Request sent.' ), 'Safe diagnostic logging should remain.' );
        } finally { unlink( $log ); }
    } else {
        throw new RuntimeException( 'Unknown test case.' );
    }
    echo "PASS $case\n";
} catch ( Throwable $e ) {
    while ( ob_get_level() ) { ob_end_clean(); }
    fwrite( STDERR, 'FAIL ' . $case . ': ' . get_class( $e ) . ': ' . $e->getMessage() . "\n" );
    exit( 1 );
}
