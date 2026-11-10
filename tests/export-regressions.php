<?php
// Run with: wp eval-file wp-content/plugins/swtch/tests/export-regressions.php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'DOING_AJAX' ) ) { define( 'DOING_AJAX', true ); }
$messages = [];
function swtch_check_regression( $ok, $message ) {
    if ( ! $ok ) { throw new RuntimeException( $message ); }
    $GLOBALS['messages'][] = 'PASS: ' . $message;
}
$users = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
wp_set_current_user( $users[0] );
$_REQUEST['nonce'] = wp_create_nonce( 'swtch_ajax' );
add_filter( 'wp_die_ajax_handler', function () {
    return function () { throw new RuntimeException( 'swtch-test-json' ); };
} );
function swtch_test_ajax_failure( $callback ) {
    ob_start();
    try {
        $callback();
    } catch ( RuntimeException $e ) {
        if ( $e->getMessage() !== 'swtch-test-json' ) { ob_end_clean(); throw $e; }
    }
    $json = json_decode( ob_get_clean(), true );
    swtch_check_regression( isset( $json['success'] ) && false === $json['success'], $callback . ' blocks unsafe job' );
}
$original_optimization = get_option( 'swtch_optimize_javascript', null );
try {
delete_option( 'swtch_optimize_javascript' );
swtch_check_regression( ! swtch_javascript_optimization_enabled(), 'Optimization is disabled by default' );
$original_script = '<script src="/' . swtch_get_asset_base() . '/plugins/swtch/tests/failure-fixture.js"></script>';
swtch_check_regression( swtch_bundle_javascript( $original_script ) === $original_script, 'Disabled optimization retains original script tags' );
$fail_node_disabled = function () { throw new RuntimeException( 'Disabled optimization tried to start Node' ); };
add_filter( 'swtch_node_binary', $fail_node_disabled );
try {
    $disabled_result = swtch_generate_static_page( home_url( '/' ) );
    swtch_check_regression( $disabled_result['success'] && empty( $disabled_result['warnings'] ), 'Disabled build succeeds without invoking Node' );
} finally {
    remove_filter( 'swtch_node_binary', $fail_node_disabled );
}
update_option( 'swtch_optimize_javascript', 1 );
swtch_check_regression( swtch_javascript_optimization_enabled(), 'Optimization can be enabled' );
$urls = swtch_get_export_urls();
$job = [ 'urls' => $urls, 'page_results' => [], 'build_ready' => true ];
foreach ( $urls as $i => $url ) { $job['page_results'][ $i ] = [ 'success' => true ]; }
$job['deployment_manifest'] = swtch_build_deployment_manifest();
swtch_check_regression( true === swtch_validate_build_pages( $job ), 'Every expected exported page exists and is nonempty' );
swtch_check_regression( true === swtch_validate_deployment_job( $job ), 'Complete build and page hashes validate' );
$bad = $job;
unset( $bad['page_results'][0] );
swtch_check_regression( is_wp_error( swtch_validate_build_pages( $bad ) ), 'Missing page result blocks build' );
$bad['page_results'][0] = [ 'success' => false, 'message' => 'Simulated failure' ];
swtch_check_regression( is_wp_error( swtch_validate_build_pages( $bad ) ), 'Failed page blocks build' );
$old = $job;
unset( $old['build_ready'] );
swtch_check_regression( is_wp_error( swtch_validate_deployment_job( $old ) ), 'Pre-fix deployment jobs cannot be reused' );
$changed = $job;
foreach ( $changed['deployment_manifest'] as &$item ) {
    if ( basename( $item['relative_path'] ) === 'index.html' ) { $item['hash'] = str_repeat( '0', 64 ); break; }
}
unset( $item );
swtch_check_regression( is_wp_error( swtch_validate_deployment_job( $changed ) ), 'Changed page hash blocks deployment' );
$id = 'swtch-regression-' . wp_generate_uuid4();
$_POST['job_id'] = $id;
try {
    $bad['stale_files'] = [ 'index.html' ];
    swtch_save_job( $id, $bad );
    foreach ( [ 'swtch_ajax_finalize_build', 'swtch_ajax_deploy_file', 'swtch_ajax_delete_stale_file', 'swtch_ajax_finalize_deployment' ] as $callback ) {
        swtch_test_ajax_failure( $callback );
    }
    $job['stale_files'] = [ 'index.html' ];
    swtch_save_job( $id, $job );
    swtch_test_ajax_failure( 'swtch_ajax_delete_stale_file' );
    swtch_test_ajax_failure( 'swtch_ajax_finalize_deployment' );
    // Simulate process failure through the same public export function.
    $fail_node = function () { return '/bin/false'; };
    add_filter( 'swtch_node_binary', $fail_node );
    // Use a temporary unique comment to force a new content hash without touching sources.
    $inject = function ( $pre, $args, $url ) {
        if ( $url !== home_url( '/' ) ) { return $pre; }
        remove_filter( 'pre_http_request', $GLOBALS['swtch_inject_test'], 10 );
        $response = wp_remote_get( $url, $args );
        add_filter( 'pre_http_request', $GLOBALS['swtch_inject_test'], 10, 3 );
        if ( ! is_wp_error( $response ) ) {
            $response['body'] .= '<script src="/wp-content/plugins/swtch/tests/failure-fixture.js"></script>';
        }
        return $response;
    };
    $GLOBALS['swtch_inject_test'] = $inject;
    add_filter( 'pre_http_request', $inject, 10, 3 );
    try { $result = swtch_generate_static_page( home_url( '/' ) ); }
    finally {
        remove_filter( 'pre_http_request', $inject, 10 );
        remove_filter( 'swtch_node_binary', $fail_node );
    }
    swtch_check_regression( $result['success'] && ! empty( $result['warnings'] ), 'Minifier failure still saves HTML with warning' );
    $result = swtch_generate_static_page( home_url( '/' ) );
    $fixture_export = swtch_asset_url_to_export_path( '/' . swtch_get_asset_base() . '/plugins/swtch/tests/failure-fixture.js' );
    if ( is_file( $fixture_export ) ) { unlink( $fixture_export ); }
    swtch_check_regression( $result['success'] && empty( $result['warnings'] ), 'Homepage regenerated with working minifier' );
} finally {
    delete_transient( swtch_job_key( $id ) );
}
echo implode( "\n", $messages ) . "\n";
} finally {
    if ( null === $original_optimization ) { delete_option( 'swtch_optimize_javascript' ); }
    else { update_option( 'swtch_optimize_javascript', $original_optimization ); }
}
