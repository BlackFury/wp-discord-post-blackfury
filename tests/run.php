<?php
$cases = array( 'render', 'legacy-get', 'get', 'missing-nonce', 'bad-nonce', 'array-nonce', 'wrong-action-nonce', 'expired-nonce', 'subscriber', 'anonymous', 'query-only-nonce', 'valid-post', 'settings', 'publish-once', 'log-redaction', 'routing', 'settings-save', 'fingerprint' );
$cases = array_merge( $cases, array( 'relay-http-failure', 'relay-delivery', 'relay-rate-limit', 'relay-uncertain', 'relay-route', 'relay-skip', 'relay-preview', 'relay-admin', 'relay-override', 'relay-private', 'relay-rest', 'relay-lock' ) );
$failed = 0;
foreach ( $cases as $case ) {
    $command = escapeshellarg( PHP_BINARY ) . ' -c ' . escapeshellarg( php_ini_loaded_file() ?: '' ) . ' ' . escapeshellarg( __DIR__ . '/case.php' ) . ' ' . escapeshellarg( $case );
    $lines = array();
    exec( $command . ' 2>&1', $lines, $status );
    $output = trim( implode( "\n", $lines ) );
    echo $output . "\n";
    if ( 0 !== $status || 'PASS ' . $case !== $output ) {
        ++$failed;
        if ( 0 === $status ) { echo "FAIL $case: unexpected diagnostic output\n"; }
    }
}
echo count( $cases ) . " cases, $failed failures\n";
exit( $failed ? 1 : 0 );
