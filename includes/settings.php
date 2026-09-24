<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function swtch_get_asset_base() {

    $asset_base = get_option(
        'swtch_asset_base',
        'assets'
    );

    $asset_base = sanitize_title( $asset_base );

    if ( empty( $asset_base ) ) {
        $asset_base = 'assets';
    }

    return $asset_base;
}

function swtch_get_live_site_url() {

    $url = get_option(
        'swtch_live_site_url',
        ''
    );

    $url = trim( $url );

    if ( empty( $url ) ) {
        return '';
    }

    return untrailingslashit( esc_url_raw( $url ) );
}

/**
 * User-entered remote ignore rules, one rule per line.
 */
function swtch_get_remote_ignore_rules_text() {
    return (string) get_option( 'swtch_remote_ignore_paths', '' );
}

/**
 * Deployment settings.
 */
function swtch_get_deployment_protocol() {

    $protocol = sanitize_key(
        get_option(
            'swtch_deployment_protocol',
            'ftp'
        )
    );

    if ( ! in_array( $protocol, [ 'ftp', 'sftp' ], true ) ) {
        return 'ftp';
    }

    return $protocol;
}

/**
 * Get the configured deployment host.
 */
function swtch_get_deployment_host() {

    return trim(
        (string) get_option(
            'swtch_deployment_host',
            ''
        )
    );
}

/**
 * Get the configured deployment port.
 */
function swtch_get_deployment_port() {

    $protocol = swtch_get_deployment_protocol();

    $default_port =
        'sftp' === $protocol
            ? 22
            : 21;

    $port = (int) get_option(
        'swtch_deployment_port',
        $default_port
    );

    if ( $port < 1 || $port > 65535 ) {
        return $default_port;
    }

    return $port;
}

/**
 * Get the configured deployment username.
 */
function swtch_get_deployment_username() {

    return trim(
        (string) get_option(
            'swtch_deployment_username',
            ''
        )
    );
}

/**
 * Get the configured deployment target directory.
 */
function swtch_get_deployment_remote_path() {

    $path = trim(
        (string) get_option(
            'swtch_deployment_remote_path',
            '/'
        )
    );

    return empty( $path ) ? '/' : $path;
}
