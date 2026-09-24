<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Return the URLs SWTCH should export.
 */
function swtch_get_export_urls() {

    $urls = [ home_url( '/' ) ];

    $posts = get_posts( [
        'post_type'      => [ 'page', 'post' ],
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ] );

    foreach ( $posts as $post ) {
        $permalink = get_permalink( $post->ID );

        if ( $permalink ) {
            $urls[] = $permalink;
        }
    }

    return array_values( array_unique( $urls ) );
}

/**
 * AJAX guard shared by every SWTCH job endpoint.
 */
function swtch_ajax_require_admin() {
    check_ajax_referer( 'swtch_ajax', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [
            'message' => 'You do not have permission to run SWTCH.',
        ], 403 );
    }
}

/**
 * Build a user-scoped transient key.
 */
function swtch_job_key( $job_id ) {
    return 'swtch_job_' . get_current_user_id() . '_' . sanitize_key( $job_id );
}

/**
 * Read a job from a transient.
 */
function swtch_get_job( $job_id ) {
    $job = get_transient( swtch_job_key( $job_id ) );
    return is_array( $job ) ? $job : false;
}

/**
 * Persist a job for 12 hours.
 */
function swtch_save_job( $job_id, $job ) {
    set_transient( swtch_job_key( $job_id ), $job, 12 * HOUR_IN_SECONDS );
}

/**
 * Start a new build and return the build manifest.
 */
function swtch_ajax_start_build() {

    swtch_ajax_require_admin();

    $urls   = swtch_get_export_urls();
    $job_id = wp_generate_uuid4();

    $job = [
        'id'                  => $job_id,
        'created'             => time(),
        'urls'                => $urls,
        'page_results'        => [],
        'deployment_manifest' => [],
    ];

    swtch_save_job( $job_id, $job );

    wp_send_json_success( [
        'job_id' => $job_id,
        'total'  => count( $urls ),
        'urls'   => $urls,
    ] );
}
add_action( 'wp_ajax_swtch_start_build', 'swtch_ajax_start_build' );

/**
 * Generate exactly one page from the server-side build manifest.
 */
function swtch_ajax_build_page() {

    swtch_ajax_require_admin();

    $job_id = isset( $_POST['job_id'] )
        ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) )
        : '';

    $index = isset( $_POST['index'] )
        ? absint( $_POST['index'] )
        : -1;

    $job = swtch_get_job( $job_id );

    if ( false === $job ) {
        wp_send_json_error( [ 'message' => 'Build job not found or expired.' ], 404 );
    }

    if ( ! isset( $job['urls'][ $index ] ) ) {
        wp_send_json_error( [ 'message' => 'Invalid build manifest index.' ], 400 );
    }

    $url    = $job['urls'][ $index ];
    $result = swtch_generate_static_page( $url );

    $job['page_results'][ $index ] = $result;
    swtch_save_job( $job_id, $job );

    wp_send_json_success( [
        'index'   => $index,
        'url'     => $url,
        'result'  => $result,
        'current' => $index + 1,
        'total'   => count( $job['urls'] ),
    ] );
}
add_action( 'wp_ajax_swtch_build_page', 'swtch_ajax_build_page' );

/**
 * Finalize a build: create sitemap.xml and construct the deployment manifest.
 */
function swtch_ajax_finalize_build() {

    swtch_ajax_require_admin();

    $job_id = isset( $_POST['job_id'] )
        ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) )
        : '';

    $job = swtch_get_job( $job_id );

    if ( false === $job ) {
        wp_send_json_error( [ 'message' => 'Build job not found or expired.' ], 404 );
    }

    $failed = array_filter(
        $job['page_results'],
        static function ( $result ) {
            return empty( $result['success'] );
        }
    );

    $sitemap_result = swtch_export_wordpress_sitemap( $job['urls'] );
    $manifest       = swtch_build_deployment_manifest();

    $job['deployment_manifest'] = $manifest;
    $job['sitemap_result']       = $sitemap_result;
    swtch_save_job( $job_id, $job );

    wp_send_json_success( [
        'pages_total'      => count( $job['urls'] ),
        'pages_failed'     => count( $failed ),
        'sitemap_created'  => false !== $sitemap_result,
        'deployment_total' => count( $manifest ),
        'manifest'         => $manifest,
    ] );
}
add_action( 'wp_ajax_swtch_finalize_build', 'swtch_ajax_finalize_build' );

/**
 * Upload exactly one file from the deployment manifest.
 *
 * The transport function is deliberately isolated so FTP/SFTP can be added
 * without changing the AJAX orchestration or progress UI.
 */
function swtch_ajax_deploy_file() {

    swtch_ajax_require_admin();

    $job_id = isset( $_POST['job_id'] )
        ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) )
        : '';

    $index = isset( $_POST['index'] )
        ? absint( $_POST['index'] )
        : -1;
    
    $password = isset( $_POST['password'] )
        ? (string) wp_unslash( $_POST['password'] )
        : '';

    $job = swtch_get_job( $job_id );

    if ( false === $job ) {
        wp_send_json_error( [ 'message' => 'Deployment job not found or expired.' ], 404 );
    }

    if ( ! isset( $job['deployment_manifest'][ $index ] ) ) {
        wp_send_json_error( [ 'message' => 'Invalid deployment manifest index.' ], 400 );
    }

    $item = $job['deployment_manifest'][ $index ];

    // Defense in depth: enforce ignore rules again at upload time.
    if ( swtch_is_remote_path_ignored( $item['relative_path'] ) ) {
        wp_send_json_success( [
            'index'   => $index,
            'skipped' => true,
            'file'    => $item['relative_path'],
            'message' => 'Skipped by remote ignore rule.',
        ] );
    }

    if ( ! function_exists( 'swtch_upload_file_to_remote' ) ) {
        wp_send_json_error( [
            'message' => 'Remote transport is not configured yet.',
            'file'    => $item['relative_path'],
        ], 501 );
    }

    $result = swtch_upload_file_to_remote(
        $item['absolute_path'],
        $item['relative_path'],
        $password
    );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [
            'message' => $result->get_error_message(),
            'file'    => $item['relative_path'],
        ], 500 );
    }

    wp_send_json_success( [
        'index'   => $index,
        'skipped' => false,
        'file'    => $item['relative_path'],
        'message' => 'Uploaded.',
    ] );
}
add_action( 'wp_ajax_swtch_deploy_file', 'swtch_ajax_deploy_file' );
