<?php
load_subject();
if ( 'independent-migrate' === $case ) {
    $source = 'wp_discord_post_webhook_url';
    $target = 'blackfury_relay_post_webhook_url';
    $id = wp_insert_post( array( 'post_title' => 'Migration fixture', 'post_status' => 'draft' ) );
    $old_url = 'https://example.invalid/original';
    update_option( $source, $old_url );
    delete_option( $target );
    delete_option( 'blackfury_relay_import_finished' );
    delete_option( 'blackfury_relay_import_cursor' );
    update_option( 'wp_discord_post_message_format', 'Original %title%' );
    update_option( 'blackfury_relay_post_message_format', 'New %title%' );
    add_post_meta( $id, '_wp_discord_post_published', 'yes' );
    $result = BlackFury_Relay_Migration::import_batch();
    expect_true( true === $result, 'Small import must finish in one batch.' );
    expect_true( $old_url === get_option( $target ) && $old_url === get_option( $source ), 'Migration must copy the webhook without altering the source.' );
    expect_true( 'New %title%' === get_option( 'blackfury_relay_post_message_format' ), 'Migration overwrote a new setting.' );
    expect_true( 'yes' === get_post_meta( $id, '_blackfury_relay_published', true ) && 'yes' === get_post_meta( $id, '_wp_discord_post_published', true ), 'Migration lost a publication marker.' );
    wp_publish_post( $id );
    expect_true( ! wp_next_scheduled( BlackFury_Relay_Delivery::EVENT, array( $id ) ), 'Already sent article was queued again.' );
    update_option( $target, 'https://example.invalid/edited' );
    BlackFury_Relay_Migration::import_batch();
    expect_true( 'https://example.invalid/edited' === get_option( $target ), 'Re-running the import overwrote a new setting.' );
    delete_post_meta( $id, '_wp_discord_post_published' );
} elseif ( 'independent-batch' === $case ) {
    delete_option( BlackFury_Relay_Migration::FINISHED );
    delete_option( BlackFury_Relay_Migration::CURSOR );
    $first = wp_insert_post( array( 'post_title' => 'Migration batch fixture', 'post_status' => 'draft' ) );
    $last = wp_insert_post( array( 'post_title' => 'Migration batch tail', 'post_status' => 'draft' ) );
    try {
        for ( $i = 0; $i < BlackFury_Relay_Migration::BATCH; $i++ ) {
            add_post_meta( $first, '_wp_discord_post_published', 'yes' );
        }
        add_post_meta( $last, '_wp_discord_post_published', 'yes' );
        expect_true( false === BlackFury_Relay_Migration::import_batch(), 'An import with more than one batch finished prematurely.' );
        expect_true( BlackFury_Relay_Plugin::delivery_paused() && ! get_post_meta( $last, '_blackfury_relay_published', true ), 'Relay failed to pause mid-import or ignored the batch boundary.' );
        expect_true( true === BlackFury_Relay_Migration::import_batch(), 'The second batch did not complete.' );
        expect_true( ! BlackFury_Relay_Plugin::delivery_paused() && 'yes' === get_post_meta( $last, '_blackfury_relay_published', true ), 'Relay failed to resume or lost the final marker.' );
        expect_true( array( 'yes' ) === get_post_meta( $first, '_blackfury_relay_published' ), 'Duplicate source markers were copied multiple times.' );
    } finally {
        delete_post_meta( $first, '_wp_discord_post_published' );
        delete_post_meta( $last, '_wp_discord_post_published' );
        delete_option( BlackFury_Relay_Migration::CURSOR );
    }
} elseif ( 'independent-overlap' === $case ) {
    $id = wp_insert_post( array( 'post_title' => 'Overlap fixture', 'post_status' => 'draft' ) );
    add_filter( 'option_active_plugins', static function () { return array( 'wp-discord-post/wp-discord-post.php' ); } );
    expect_true( is_wp_error( BlackFury_Relay_Migration::import_batch() ), 'Migration must stop while the original is active.' );
    wp_publish_post( $id );
    expect_true( ! wp_next_scheduled( BlackFury_Relay_Delivery::EVENT, array( $id ) ), 'Relay queued a notification alongside the original.' );
} else {
    $prefix = 'https://github.com/BlackFury/wp-discord-post-blackfury/releases/download/v2.4.1/';
    $zip = 'blackfury-relay-2.4.1.zip';
    $content = 'synthetic delivery fixture';
    $release = array( 'tag_name' => 'v2.4.1', 'draft' => false, 'prerelease' => false, 'assets' => array(
        array( 'name' => $zip, 'browser_download_url' => $prefix . $zip, 'digest' => 'sha256:' . hash( 'sha256', $content ) )
    ) );
    $update = BlackFury_Relay_Updates::parse_release( $release );
    expect_true( '2.4.1' === $update['version'] && $prefix . $zip === $update['package'], 'Official stable asset was rejected.' );
    $preview = array_replace( $release, array( 'prerelease' => true ) );
    expect_true( false === BlackFury_Relay_Updates::parse_release( $preview ), 'A beta was offered automatically.' );
    $foreign = $release;
    $foreign['assets'][0]['browser_download_url'] = 'https://example.invalid/' . $zip;
    expect_true( false === BlackFury_Relay_Updates::parse_release( $foreign ), 'Update accepted a foreign package URL.' );
    set_site_transient( BlackFury_Relay_Updates::CACHE, array( 'release' => $update ) );
    $metadata = array( 'UpdateURI' => BlackFury_Relay_Updates::REPOSITORY, 'Version' => '2.2.0-beta.2' );
    $offered = apply_filters( 'update_plugins_github.com', false, $metadata, BlackFury_Relay_Updates::PLUGIN, array() );
    expect_true( '2.4.1' === $offered['version'], 'Native WordPress update filter did not offer Relay.' );
    expect_true( false === apply_filters( 'update_plugins_github.com', false, $metadata, 'wp-discord-post/wp-discord-post.php', array() ), 'Relay offered an update for WP Discord Post.' );
    $metadata['Version'] = '2.5.0';
    $current = apply_filters( 'update_plugins_github.com', false, $metadata, BlackFury_Relay_Updates::PLUGIN, array() );
    expect_true( '2.5.0' === $current['version'] && '' === $current['package'], 'Relay offered a downgrade or hid native automatic updates.' );
    $retirement = array_replace( $update, array( 'version' => '2.5.0' ) );
    set_site_transient( BlackFury_Relay_Updates::CACHE, array( 'release' => $retirement ) );
    delete_option( 'blackfury_relay_import_finished' );
    update_option( 'blackfury_relay_import_cursor', 20, false );
    $metadata['Version'] = '2.4.1';
    $paused = apply_filters( 'update_plugins_github.com', false, $metadata, BlackFury_Relay_Updates::PLUGIN, array() );
    expect_true( '' === $paused['package'], 'Relay offered 2.5 during an incomplete migration.' );
    delete_option( 'blackfury_relay_import_cursor' );
    set_site_transient( BlackFury_Relay_Updates::CACHE, array( 'release' => $update ) );
    $file = wp_tempnam( 'relay-update-fixture' );
    try {
        file_put_contents( $file, $content );
        // WordPress bulk updates, including background updates, omit action from hook_extra.
        $bulk = array( 'plugin' => BlackFury_Relay_Updates::PLUGIN );
        expect_true( $file === apply_filters( 'upgrader_pre_download', $file, $update['package'], null, $bulk ), 'Bulk update rejected the matching digest.' );
        file_put_contents( $file, 'corrupted fixture' );
        $rejected = apply_filters( 'upgrader_pre_download', $file, $update['package'], null, $bulk );
        expect_true( is_wp_error( $rejected ) && 'relay_update_checksum' === $rejected->get_error_code(), 'Bulk update did not reject a corrupted ZIP.' );
    } finally {
        wp_delete_file( $file );
        delete_site_transient( BlackFury_Relay_Updates::CACHE );
    }
    unset( $release['assets'][0]['digest'] );
    expect_true( false === BlackFury_Relay_Updates::parse_release( $release ), 'Update accepted a package without a digest.' );
}
