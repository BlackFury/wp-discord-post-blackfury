<?php
load_subject();
$relay = BlackFury_Relay_Plugin::instance()->post;
$status = 200;
$response = function () use ( &$status ) {
    return is_int( $status ) ? array(
        'headers' => array( 'retry-after' => '45' ),
        'body' => '{"id":"123456789012345678","channel_id":"234567890123456789"}',
        'response' => array( 'code' => $status, 'message' => 'Synthetic' ),
    ) : new WP_Error( 'fixture_timeout', 'Synthetic timeout' );
};
add_filter( 'pre_http_request', $response, 100 );
$id = wp_insert_post( array( 'post_title' => 'Relay fixture', 'post_excerpt' => 'Example excerpt', 'post_status' => 'draft' ), true );
expect_true( is_int( $id ) && $id > 0, 'Fixture post creation failed.' );
if ( 'relay-http-failure' === $case ) {
    $status = 401;
    ( new BlackFury_Relay_Plugin_HTTP( 'post' ) )->process( 'Fixture', array(), $id );
    expect_true( ! get_post_meta( $id, '_blackfury_relay_published', true ), 'HTTP 401 was recorded as a success.' );
    return;
}
if ( 'relay-rate-limit' === $case ) { $status = 429; }
if ( 'relay-uncertain' === $case ) { $status = 'timeout'; }
if ( 'relay-route' === $case ) {
    update_option( 'blackfury_relay_post_disable_embed', 'no' );
    $category = wp_insert_term( 'Relay gaming ' . $id, 'category' );
    wp_set_post_categories( $id, array( $category['term_id'] ) );
    $url = 'https://discord.com/api/webhooks/123456789012345678/' . str_repeat( 'a', 30 );
    update_option( 'blackfury_relay_channels', array( 'gaming' => array( 'label' => 'Gaming', 'url' => $url, 'guild' => '345678901234567890', 'color' => '#7869ef' ) ) );
    update_term_meta( $category['term_id'], '_blackfury_relay_channel', 'gaming' );
}
if ( 'relay-skip' === $case ) { update_post_meta( $id, '_blackfury_relay_override', 'off' ); }
if ( 'relay-private' === $case ) { wp_update_post( array( 'ID' => $id, 'post_password' => 'fixture-password' ) ); }
if ( 'relay-rest' === $case ) {
    BlackFury_Relay_Delivery::register_editor_meta();
    $route = '/wp/v2/posts/' . $id;
    $request = new WP_REST_Request( 'POST', $route );
    $request->set_body_params( array( 'status' => 'publish', 'meta' => array( BlackFury_Relay_Delivery::OVERRIDE => 'off' ) ) );
    $response = rest_do_request( $request );
    expect_true( 200 === $response->get_status(), 'Block editor REST publication failed: ' . wp_json_encode( $response->get_data() ) );
    expect_true( 'off' === get_post_meta( $id, BlackFury_Relay_Delivery::OVERRIDE, true ), 'Block editor override was not saved.' );
    expect_true( wp_next_scheduled( BlackFury_Relay_Delivery::EVENT, array( $id ) ), 'REST publication was not queued.' );
    do_action( BlackFury_Relay_Delivery::EVENT, $id );
    expect_true( 'skipped' === get_post_meta( $id, BlackFury_Relay_Delivery::JOB, true )['status'] && ! $GLOBALS['requests'], 'REST publication ignored the opt-out.' );
    wp_set_current_user( 0 );
    $public = rest_do_request( new WP_REST_Request( 'GET', $route ) )->get_data();
    expect_true( ! array_key_exists( BlackFury_Relay_Delivery::OVERRIDE, (array) ( $public['meta'] ?? array() ) ), 'Routing data appeared in the public REST response.' );
    return;
}
if ( 'relay-admin' === $case || 'relay-override' === $case ) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $secret = 'https://discord.com/api/webhooks/234567890123456789/' . str_repeat( 'b', 30 );
    $_POST = array( 'operation' => 'add', 'label' => 'Editorial fixtures', 'url' => $secret, '_wpnonce' => wp_create_nonce( BlackFury_Relay_Dashboard::ACTION ) );
    wp_set_current_user( $GLOBALS['subscriber_id'] );
    $rejected = false;
    try {
        do_action( 'admin_post_' . BlackFury_Relay_Dashboard::ACTION );
    } catch ( TestStop $e ) {
        $rejected = 403 === $e->status;
    }
    expect_true( $rejected && ! BlackFury_Relay_Delivery::channels(), 'Unauthorized user configured a destination.' );
    wp_set_current_user( $GLOBALS['admin_id'] );
    try {
        do_action( 'admin_post_' . BlackFury_Relay_Dashboard::ACTION );
    } catch ( TestRedirect $e ) {
        expect_true( false !== strpos( $e->url, 'bfr_notice=saved' ), 'Channel was not added.' );
    }
    $channels = BlackFury_Relay_Delivery::channels();
    $channel_id = array_key_first( $channels );
    expect_true( $secret === $channels[ $channel_id ]['url'], 'Channel URL did not persist.' );
    if ( 'relay-admin' === $case ) {
        $_POST = array( 'operation' => 'test', 'channel' => $channel_id, '_wpnonce' => wp_create_nonce( BlackFury_Relay_Dashboard::ACTION ) );
        try {
            do_action( 'admin_post_' . BlackFury_Relay_Dashboard::ACTION );
        } catch ( TestRedirect $e ) {
            expect_true( false !== strpos( $e->url, 'bfr_notice=test_ok' ), 'The UI did not acknowledge the connection test.' );
        }
        expect_true( count( $GLOBALS['requests'] ) === 1, 'Test did not send one webhook request.' );
        do_action( 'admin_menu' );
        ob_start();
        do_action( 'toplevel_page_blackfury-relay' );
        $screen = ob_get_clean();
        expect_true( false !== strpos( $screen, 'Delivery journal' ) && false !== strpos( $screen, 'Editorial fixtures' ), 'Relay settings screen did not render.' );
        expect_true( false === strpos( $screen, $secret ), 'Webhook secret leaked to settings HTML.' );
        return;
    }
    $_POST = array( 'blackfury_relay_override' => $channel_id, 'blackfury_relay_article_nonce' => wp_create_nonce( 'blackfury_relay_article_' . $id ) );
    wp_publish_post( $id );
    wp_clear_scheduled_hook( 'blackfury_relay_deliver', array( $id ) );
    do_action( 'blackfury_relay_deliver', $id );
    expect_true( 1 === count( $GLOBALS['requests'] ) && $secret === strtok( $GLOBALS['requests'][0]['url'], '?' ), 'The editor choice saved at publication did not win.' );
    delete_option( BlackFury_Relay_Delivery::CHANNELS );
    return;
}
if ( 'relay-preview' === $case ) {
    $preview = $relay->preview( get_post( $id ) );
    expect_true( 'Relay fixture' === $preview['embed']['title'] && 'Example excerpt' === $preview['embed']['description'], 'Preview differs from the outgoing card.' );
    expect_true( 0 === count( $GLOBALS['requests'] ), 'Preview called Discord.' );
    return;
}
if ( 'relay-delivery' === $case ) {
    $date = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
    wp_update_post( array( 'ID' => $id, 'post_status' => 'future', 'post_date' => get_date_from_gmt( $date ), 'post_date_gmt' => $date, 'edit_date' => true ) );
    expect_true( 'future' === get_post_status( $id ), 'Fixture must be scheduled before publication.' );
    expect_true( ! wp_next_scheduled( 'blackfury_relay_deliver', array( $id ) ), 'Unpublished scheduled article was queued early.' );
}
if ( 'relay-delivery' === $case ) {
    // Make the scheduled article due in both WordPress and the test's clock.
    $GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 10 ) ), array( 'ID' => $id ) );
    clean_post_cache( $id );
    do_action( 'publish_future_post', $id );
} else {
    wp_publish_post( $id );
}
expect_true( 0 === count( $GLOBALS['requests'] ), 'Publishing made a network request.' );
expect_true( wp_next_scheduled( 'blackfury_relay_deliver', array( $id ) ), 'Published article was not queued.' );
wp_clear_scheduled_hook( 'blackfury_relay_deliver', array( $id ) );
if ( 'relay-lock' === $case ) {
    $key = 'blackfury_relay_lock_' . $id;
    add_option( $key, ( time() + 300 ) . ':fixture', '', false );
    do_action( BlackFury_Relay_Delivery::EVENT, $id );
    expect_true( ! $GLOBALS['requests'] && wp_next_scheduled( BlackFury_Relay_Delivery::EVENT, array( $id ) ), 'Contended cron event was lost or sent.' );
    delete_option( $key );
    wp_clear_scheduled_hook( BlackFury_Relay_Delivery::EVENT, array( $id ) );
}
do_action( 'blackfury_relay_deliver', $id );
$job = get_post_meta( $id, '_blackfury_relay_delivery', true );
if ( 'relay-rate-limit' === $case ) {
    expect_true( 'pending' === $job['status'] && $job['next'] >= time() + 43, 'Discord rate limit was not respected.' );
    do_action( 'blackfury_relay_deliver', $id );
    expect_true( 1 === count( $GLOBALS['requests'] ), 'Early rerun ignored Retry-After.' );
    expect_true( ! get_post_meta( $id, '_blackfury_relay_published', true ), 'Rate limit marked the post as sent.' );
} elseif ( 'relay-uncertain' === $case ) {
    expect_true( 'uncertain' === $job['status'], 'A network timeout must be reported as uncertain.' );
    do_action( 'blackfury_relay_deliver', $id );
    expect_true( 1 === count( $GLOBALS['requests'] ), 'An uncertain delivery was retried automatically.' );
    $handler = array_values( $GLOBALS['wp_filter'][ BlackFury_Relay_Delivery::EVENT ]->callbacks[10] )[0]['function'][0];
    expect_true( false === $handler->retry( $id, false ), 'Uncertain delivery allowed a blind retry.' );
    expect_true( true === $handler->retry( $id, true ), 'Explicit uncertain retry was not queued.' );
    $status = 200;
    do_action( BlackFury_Relay_Delivery::EVENT, $id );
    expect_true( 2 === count( $GLOBALS['requests'] ) && 'accepted' === get_post_meta( $id, BlackFury_Relay_Delivery::JOB, true )['status'], 'Explicit retry did not resolve the delivery.' );
} elseif ( 'relay-skip' === $case || 'relay-private' === $case ) {
    expect_true( 'skipped' === $job['status'] && 0 === count( $GLOBALS['requests'] ), 'Per-article opt-out did not suppress delivery.' );
} else {
    expect_true( 'accepted' === $job['status'] && '123456789012345678' === $job['message'], 'Discord acceptance was not recorded.' );
    expect_true( 'yes' === get_post_meta( $id, '_blackfury_relay_published', true ), 'Legacy sent marker missing.' );
    expect_true( 1 === count( $GLOBALS['requests'] ), 'Wrong request count.' );
    $request = $GLOBALS['requests'][0];
    expect_true( false !== strpos( $request['url'], 'wait=true' ), 'Did not request Discord acknowledgement.' );
    $body = json_decode( $request['args']['body'] );
    expect_true( array() === $body->allowed_mentions->parse, 'Mass mentions were not explicitly disabled.' );
    expect_true( empty( $request['args']['headers']['Authorization'] ), 'The webhook sent the legacy bot token.' );
    if ( 'relay-route' === $case ) {
        expect_true( $url === strtok( $request['url'], '?' ), 'Rule did not select the category channel.' );
        expect_true( hexdec( '7869ef' ) === $body->embeds[0]->color, 'Configured embed accent was not sent.' );
    }
}
