<?php
/** Read-only: wp eval-file /private/path/settings-fingerprint.php */
global $wpdb;
if ( ! defined( 'ABSPATH' ) || ! isset( $wpdb ) ) {
    fwrite( STDERR, "Run with wp eval-file, inside the intended site.\n" );
    exit( 2 );
}
$rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s ORDER BY option_name",
    $wpdb->esc_like( 'wp_discord_' ) . '%',
    $wpdb->esc_like( 'blackfury_relay_' ) . '%'
), ARRAY_A );
$options = array();
foreach ( $rows as $row ) {
    $options[ $row['option_name'] ] = hash( 'sha256', $row['option_value'] );
}
$meta = $wpdb->get_results( $wpdb->prepare(
    "SELECT post_id, meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s) ORDER BY post_id, meta_id",
    '_wp_discord_post_published',
    '_blackfury_relay_published'
), ARRAY_A );
echo wp_json_encode( array(
    'format' => 1,
    'options' => $options,
    'publication_markers_count' => count( $meta ),
    'publication_markers_sha256' => hash( 'sha256', wp_json_encode( $meta ) ),
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
