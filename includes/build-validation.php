<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Reject failed, skipped, or missing pages before any remote changes. */
function swtch_validate_build_pages( $job ) {
    if ( ! empty( $job['build_error'] ) ) {
        return new WP_Error( 'swtch_build_stopped', $job['build_error'] . ' Run Build Site again.' );
    }
    if ( empty( $job['urls'] ) || ! is_array( $job['urls'] ) ) {
        return new WP_Error( 'swtch_empty_build', 'Build has no pages. Deployment is blocked.' );
    }
    foreach ( $job['urls'] as $index => $url ) {
        $result = $job['page_results'][ $index ] ?? [];
        $path = swtch_get_export_path( $url );
        if ( empty( $result['success'] ) || ! is_readable( $path ) || filesize( $path ) === 0 ) {
            return new WP_Error( 'swtch_incomplete_build', 'Build is incomplete; deployment and remote cleanup are blocked. Failed or missing page: ' . $url );
        }
    }
    return true;
}

/** Every deployment request revalidates its job and exported page hashes. */
function swtch_validate_deployment_job( $job ) {
    $valid = swtch_validate_build_pages( $job );
    if ( is_wp_error( $valid ) ) { return $valid; }
    if ( empty( $job['build_ready'] ) || empty( $job['deployment_manifest'] ) ) {
        return new WP_Error( 'swtch_unfinalized_build', 'Build has not completed validation. Run Build Site again.' );
    }
    $pages = [];
    foreach ( $job['deployment_manifest'] as $item ) {
        if ( basename( $item['relative_path'] ) !== 'index.html' ) { continue; }
        $path = $item['absolute_path'];
        if ( ! is_readable( $path ) || ! hash_equals( $item['hash'], hash_file( 'sha256', $path ) ) ) {
            return new WP_Error( 'swtch_export_changed', 'Export changed after this build. Run Build Site again before deploying.' );
        }
        $pages[ $path ] = true;
    }
    foreach ( $job['urls'] as $url ) {
        $path = swtch_get_export_path( $url );
        if ( ! swtch_is_remote_path_ignored( ltrim( substr( $path, strlen( WP_CONTENT_DIR . '/swtch-export' ) ), '/' ) ) && empty( $pages[ $path ] ) ) {
            return new WP_Error( 'swtch_missing_manifest_page', 'Deployment manifest is missing a generated page. Run Build Site again.' );
        }
    }
    return true;
}

function swtch_ajax_require_valid_deployment( $job, $require_uploads = false ) {
    $valid = swtch_validate_deployment_job( $job );
    if ( is_wp_error( $valid ) ) {
        wp_send_json_error( [ 'message' => $valid->get_error_message() ], 409 );
    }
    if ( $require_uploads ) {
        foreach ( $job['deployment_manifest'] as $index => $item ) {
            if ( empty( $job['uploaded_files'][ $index ] ) ) {
                wp_send_json_error( [ 'message' => 'All uploads must succeed before cleanup or deployment completion.' ], 409 );
            }
        }
    }
}
