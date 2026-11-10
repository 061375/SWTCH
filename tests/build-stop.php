<?php
// wp eval-file wp-content/plugins/swtch/tests/build-stop.php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'DOING_AJAX' ) ) { define( 'DOING_AJAX', true ); }
$users = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
wp_set_current_user( $users[0] );
$_REQUEST['nonce'] = wp_create_nonce( 'swtch_ajax' );
add_filter( 'wp_die_ajax_handler', function () { return function () { throw new RuntimeException( 'test-json' ); }; } );
function swtch_stop_assert( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function swtch_stop_request( $callback ) {
    ob_start();
    try { $callback(); }
    catch ( RuntimeException $e ) {
        if ( $e->getMessage() !== 'test-json' ) { ob_end_clean(); throw $e; }
    }
    $result = json_decode( ob_get_clean(), true );
    swtch_stop_assert( isset( $result['success'] ) && ! $result['success'], 'Expected failure: ' . $callback );
    return $result;
}
$id = wp_generate_uuid4();
$url = home_url( '/swtch-intentional-build-failure/' );
$_POST = [ 'job_id' => $id, 'index' => 0 ];
$requests = 0;
$fail = function ( $pre, $args, $target ) use ( $url, &$requests ) {
    if ( $target !== $url ) { throw new RuntimeException( 'Unexpected fetch after failure' ); }
    $requests++;
    return new WP_Error( 'test_fetch_failed', 'Simulated page fetch failure' );
};
add_filter( 'pre_http_request', $fail, 10, 3 );
try {
    swtch_save_job( $id, [ 'urls' => [ $url, home_url( '/' ) ], 'page_results' => [] ] );
    $response = swtch_stop_request( 'swtch_ajax_build_page' );
    swtch_stop_assert( str_contains( $response['data']['message'], 'Simulated page fetch failure' ), 'Failure reason must be returned' );
    $job = swtch_get_job( $id );
    swtch_stop_assert( ! empty( $job['build_error'] ) && ! $job['build_ready'] && empty( $job['deployment_manifest'] ), 'Failed job must be invalidated' );
    $_POST['index'] = 1;
    swtch_stop_request( 'swtch_ajax_build_page' );
    swtch_stop_assert( $requests === 1, 'No second page should be requested' );
    foreach ( [ 'swtch_ajax_finalize_build', 'swtch_ajax_deploy_file', 'swtch_ajax_delete_stale_file', 'swtch_ajax_finalize_deployment' ] as $callback ) {
        swtch_stop_request( $callback );
    }
    $success = true;
} finally {
    remove_filter( 'pre_http_request', $fail, 10 );
    delete_transient( swtch_job_key( $id ) );
}
echo "PASS: Failed page returns error, stops subsequent fetches, and blocks finalization, uploads and cleanup\n";
