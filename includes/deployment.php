<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Build the deployment manifest from the completed export tree.
 *
 * Ignore rules are applied here so protected paths never enter the normal
 * upload queue. They are also checked again immediately before upload.
 */
function swtch_build_deployment_manifest() {

    $export_root = WP_CONTENT_DIR . '/swtch-export';
    $manifest    = [];

    if ( ! is_dir( $export_root ) ) {
        return $manifest;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $export_root,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ( $iterator as $file ) {

        if ( ! $file->isFile() ) {
            continue;
        }

        $absolute_path = wp_normalize_path( $file->getPathname() );
        $relative_path = ltrim(
            substr(
                $absolute_path,
                strlen( wp_normalize_path( $export_root ) )
            ),
            '/'
        );

        if ( '' === $relative_path ) {
            continue;
        }

        if ( swtch_is_remote_path_ignored( $relative_path ) ) {
            continue;
        }

        $manifest[] = [
            'relative_path' => $relative_path,
            'absolute_path' => $absolute_path,
            'size'          => (int) $file->getSize(),
            'hash'          => hash_file( 'sha256', $absolute_path ),
        ];
    }

    usort(
        $manifest,
        static function ( $a, $b ) {
            return strcmp( $a['relative_path'], $b['relative_path'] );
        }
    );

    return array_values( $manifest );
}
/**
 * Get the manifest from the last successful deployment.
 */
function swtch_get_last_deployment_manifest() {

    $manifest = get_option(
        'swtch_last_deployment_manifest',
        []
    );

    return is_array( $manifest )
        ? $manifest
        : [];
}


/**
 * Store the current manifest as the last successful deployment.
 */
function swtch_save_deployment_manifest( $manifest ) {

    $stored_manifest = [];

    foreach ( $manifest as $item ) {

        if (
            empty( $item['relative_path'] ) ||
            empty( $item['hash'] )
        ) {
            continue;
        }

        $stored_manifest[ $item['relative_path'] ] = [
            'hash' => $item['hash'],
            'size' => isset( $item['size'] )
                ? (int) $item['size']
                : 0,
        ];
    }

    update_option(
        'swtch_last_deployment_manifest',
        [
            'deployed_at' => current_time( 'mysql' ),
            'files'       => $stored_manifest,
        ],
        false
    );
}

/**
 * Test either an FTP or SFTP deployment connection.
 */
function swtch_test_deployment_connection(
    $protocol,
    $host,
    $port,
    $username,
    $password,
    $remote_path
) {

    if ( empty( $host ) ) {

        return [
            'success' => false,
            'message' => 'Deployment host is required.',
        ];
    }

    if ( empty( $username ) ) {

        return [
            'success' => false,
            'message' => 'Deployment username is required.',
        ];
    }

    if ( empty( $password ) ) {

        return [
            'success' => false,
            'message' => 'Deployment password is required.',
        ];
    }

    if ( 'sftp' === $protocol ) {

        return swtch_test_sftp_connection(
            $host,
            $port,
            $username,
            $password,
            $remote_path
        );
    }

    return swtch_test_ftp_connection(
        $host,
        $port,
        $username,
        $password,
        $remote_path
    );
}

/**
 * Test a standard FTP connection.
 */
function swtch_test_ftp_connection(
    $host,
    $port,
    $username,
    $password,
    $remote_path
) {

    if ( ! function_exists( 'ftp_connect' ) ) {

        return [
            'success' => false,
            'message' => 'The PHP FTP extension is not installed.',
        ];
    }

    $connection = @ftp_connect(
        $host,
        $port,
        15
    );

    if ( ! $connection ) {

        return [
            'success' => false,
            'message' => 'Could not connect to the FTP server.',
        ];
    }

    $authenticated = @ftp_login(
        $connection,
        $username,
        $password
    );

    if ( ! $authenticated ) {

        ftp_close( $connection );

        return [
            'success' => false,
            'message' => 'FTP authentication failed.',
        ];
    }

    @ftp_pasv( $connection, true );

    if (
        ! empty( $remote_path ) &&
        '/' !== $remote_path &&
        ! @ftp_chdir( $connection, $remote_path )
    ) {

        ftp_close( $connection );

        return [
            'success' => false,
            'message' => 'FTP login succeeded, but the remote path could not be opened.',
        ];
    }

    ftp_close( $connection );

    return [
        'success' => true,
        'message' => 'FTP connection successful.',
    ];
}

/**
 * Test an SFTP connection over SSH.
 */
function swtch_test_sftp_connection(
    $host,
    $port,
    $username,
    $password,
    $remote_path
) {

    if ( ! function_exists( 'ssh2_connect' ) ) {

        return [
            'success' => false,
            'message' => 'The PHP SSH2 extension is not installed.',
        ];
    }

    $connection = @ssh2_connect(
        $host,
        $port
    );

    if ( ! $connection ) {

        return [
            'success' => false,
            'message' => 'Could not connect to the SFTP server.',
        ];
    }

    if ( ! @ssh2_auth_password( $connection, $username, $password ) ) {

        return [
            'success' => false,
            'message' => 'SFTP authentication failed.',
        ];
    }

    $sftp = @ssh2_sftp( $connection );

    if ( ! $sftp ) {

        return [
            'success' => false,
            'message' => 'Connected, but SFTP could not be initialized.',
        ];
    }

    if ( ! empty( $remote_path ) && '/' !== $remote_path ) {

        $remote_url = sprintf(
            'ssh2.sftp://%d%s',
            intval( $sftp ),
            '/' . ltrim( $remote_path, '/' )
        );

        if ( ! @is_dir( $remote_url ) ) {

            return [
                'success' => false,
                'message' => 'SFTP login succeeded, but the remote path could not be opened.',
            ];
        }
    }

    return [
        'success' => true,
        'message' => 'SFTP connection successful.',
    ];
}
/**
 * Upload one exported file to the configured remote server.
 *
 * @param string $local_path    Absolute local path.
 * @param string $relative_path Path relative to the export root.
 * @param string $password      Deployment password.
 *
 * @return true|WP_Error
 */
function swtch_upload_file_to_remote(
    $local_path,
    $relative_path,
    $password
) {

    if ( ! is_file( $local_path ) ) {
        return new WP_Error(
            'swtch_local_file_missing',
            'Local deployment file does not exist.'
        );
    }

    if ( swtch_is_remote_path_ignored( $relative_path ) ) {
        return new WP_Error(
            'swtch_remote_path_ignored',
            'Remote path is protected by an ignore rule.'
        );
    }

    $protocol = swtch_get_deployment_protocol();
    $host     = swtch_get_deployment_host();
    $port     = swtch_get_deployment_port();
    $username = swtch_get_deployment_username();
    $base     = swtch_get_deployment_remote_path();

    if ( empty( $host ) ) {
        return new WP_Error(
            'swtch_missing_host',
            'Deployment host is not configured.'
        );
    }

    if ( empty( $username ) ) {
        return new WP_Error(
            'swtch_missing_username',
            'Deployment username is not configured.'
        );
    }

    $relative_path = ltrim(
        str_replace( '\\', '/', $relative_path ),
        '/'
    );

    $base = '/' . trim(
        str_replace( '\\', '/', $base ),
        '/'
    );

    if ( '/' === $base ) {
        $remote_path = '/' . $relative_path;
    } else {
        $remote_path = $base . '/' . $relative_path;
    }

    if ( 'sftp' === $protocol ) {
        return swtch_upload_file_sftp(
            $host,
            $port,
            $username,
            $password,
            $local_path,
            $remote_path
        );
    }

    return swtch_upload_file_ftp(
        $host,
        $port,
        $username,
        $password,
        $local_path,
        $remote_path
    );
}
/**
 * Upload one file over FTP.
 */
function swtch_upload_file_ftp(
    $host,
    $port,
    $username,
    $password,
    $local_path,
    $remote_path
) {

    if ( ! function_exists( 'ftp_connect' ) ) {
        return new WP_Error(
            'swtch_ftp_unavailable',
            'The PHP FTP extension is not available.'
        );
    }

    $connection = ftp_connect(
        $host,
        $port,
        30
    );

    if ( false === $connection ) {
        return new WP_Error(
            'swtch_ftp_connection_failed',
            'Could not connect to the FTP server.'
        );
    }

    if ( ! ftp_login( $connection, $username, $password ) ) {
        ftp_close( $connection );

        return new WP_Error(
            'swtch_ftp_login_failed',
            'FTP login failed.'
        );
    }

    ftp_pasv( $connection, true );

    $remote_directory = dirname( $remote_path );

    $mkdir_result = swtch_ftp_ensure_directory(
        $connection,
        $remote_directory
    );

    if ( is_wp_error( $mkdir_result ) ) {
        ftp_close( $connection );
        return $mkdir_result;
    }

    $uploaded = ftp_put(
        $connection,
        $remote_path,
        $local_path,
        FTP_BINARY
    );

    ftp_close( $connection );

    if ( ! $uploaded ) {
        return new WP_Error(
            'swtch_ftp_upload_failed',
            'FTP upload failed: ' . $remote_path
        );
    }

    return true;
}
/**
 * Ensure that a directory tree exists on the FTP server.
 */
function swtch_ftp_ensure_directory(
    $connection,
    $directory
) {

    $directory = str_replace(
        '\\',
        '/',
        $directory
    );

    $directory = trim(
        $directory,
        '/'
    );

    if ( '' === $directory ) {
        return true;
    }

    $parts   = explode( '/', $directory );
    $current = '';

    foreach ( $parts as $part ) {

        if ( '' === $part ) {
            continue;
        }

        $current .= '/' . $part;

        if ( @ftp_chdir( $connection, $current ) ) {
            continue;
        }

        if ( ! @ftp_mkdir( $connection, $current ) ) {
            return new WP_Error(
                'swtch_ftp_mkdir_failed',
                'Could not create remote directory: ' . $current
            );
        }
    }

    return true;
}
/**
 * Upload one file over SFTP.
 */
function swtch_upload_file_sftp(
    $host,
    $port,
    $username,
    $password,
    $local_path,
    $remote_path
) {

    if (
        ! function_exists( 'ssh2_connect' ) ||
        ! function_exists( 'ssh2_auth_password' ) ||
        ! function_exists( 'ssh2_sftp' )
    ) {
        return new WP_Error(
            'swtch_sftp_unavailable',
            'The PHP SSH2 extension is not available.'
        );
    }

    $connection = ssh2_connect(
        $host,
        $port
    );

    if ( false === $connection ) {
        return new WP_Error(
            'swtch_sftp_connection_failed',
            'Could not connect to the SFTP server.'
        );
    }

    if (
        ! ssh2_auth_password(
            $connection,
            $username,
            $password
        )
    ) {
        return new WP_Error(
            'swtch_sftp_login_failed',
            'SFTP login failed.'
        );
    }

    $sftp = ssh2_sftp( $connection );

    if ( false === $sftp ) {
        return new WP_Error(
            'swtch_sftp_init_failed',
            'Could not initialize the SFTP subsystem.'
        );
    }

    $directory = dirname( $remote_path );

    $mkdir_result = swtch_sftp_ensure_directory(
        $sftp,
        $directory
    );

    if ( is_wp_error( $mkdir_result ) ) {
        return $mkdir_result;
    }

    $remote_uri =
        'ssh2.sftp://' .
        intval( $sftp ) .
        $remote_path;

    if ( ! copy( $local_path, $remote_uri ) ) {
        return new WP_Error(
            'swtch_sftp_upload_failed',
            'SFTP upload failed: ' . $remote_path
        );
    }

    return true;
}
/**
 * Ensure that a directory tree exists on the SFTP server.
 */
function swtch_sftp_ensure_directory(
    $sftp,
    $directory
) {

    $directory = trim(
        str_replace( '\\', '/', $directory ),
        '/'
    );

    if ( '' === $directory ) {
        return true;
    }

    $parts   = explode( '/', $directory );
    $current = '';

    foreach ( $parts as $part ) {

        if ( '' === $part ) {
            continue;
        }

        $current .= '/' . $part;

        if ( file_exists( 'ssh2.sftp://' . intval( $sftp ) . $current ) ) {
            continue;
        }

        if (
            ! ssh2_sftp_mkdir(
                $sftp,
                $current,
                0755
            )
        ) {
            return new WP_Error(
                'swtch_sftp_mkdir_failed',
                'Could not create remote directory: ' . $current
            );
        }
    }

    return true;
}
/**
 * Determine whether a manifest item has changed
 * since the last successful deployment.
 */
function swtch_deployment_file_changed(
    $item,
    $previous_manifest
) {

    if (
        empty( $item['relative_path'] ) ||
        empty( $item['hash'] )
    ) {
        return true;
    }

    $previous_files = isset( $previous_manifest['files'] )
        && is_array( $previous_manifest['files'] )
            ? $previous_manifest['files']
            : [];

    $relative_path = $item['relative_path'];

    /*
     * New file.
     */
    if ( ! isset( $previous_files[ $relative_path ] ) ) {
        return true;
    }

    /*
     * Existing file whose contents changed.
     */
    if (
        empty( $previous_files[ $relative_path ]['hash'] ) ||
        ! hash_equals(
            $previous_files[ $relative_path ]['hash'],
            $item['hash']
        )
    ) {
        return true;
    }

    /*
     * Same path and same contents.
     */
    return false;
}
/**
 * Return files that existed in the previous successful deployment
 * but no longer exist in the current deployment manifest.
 */
function swtch_get_stale_deployment_files(
    $current_manifest,
    $previous_manifest
) {

    $current_paths = [];

    foreach ( $current_manifest as $item ) {

        if ( empty( $item['relative_path'] ) ) {
            continue;
        }

        $current_paths[ $item['relative_path'] ] = true;
    }

    $previous_files =
        isset( $previous_manifest['files'] ) &&
        is_array( $previous_manifest['files'] )
            ? $previous_manifest['files']
            : [];

    $stale = [];

    foreach ( array_keys( $previous_files ) as $relative_path ) {

        if ( isset( $current_paths[ $relative_path ] ) ) {
            continue;
        }

        /*
         * Never allow a stale-file cleanup to bypass
         * the remote protection rules.
         */
        if ( swtch_is_remote_path_ignored( $relative_path ) ) {
            continue;
        }

        $stale[] = $relative_path;
    }

    sort( $stale );

    return $stale;
}
/**
 * Delete one file previously deployed by SWTCH.
 *
 * @return true|WP_Error
 */
function swtch_delete_file_from_remote(
    $relative_path,
    $password
) {

    if ( swtch_is_remote_path_ignored( $relative_path ) ) {
        return new WP_Error(
            'swtch_remote_path_ignored',
            'Remote path is protected by an ignore rule.'
        );
    }

    $protocol = swtch_get_deployment_protocol();
    $host     = swtch_get_deployment_host();
    $port     = swtch_get_deployment_port();
    $username = swtch_get_deployment_username();
    $base     = swtch_get_deployment_remote_path();

    $relative_path = ltrim(
        str_replace( '\\', '/', $relative_path ),
        '/'
    );

    $base = '/' . trim(
        str_replace( '\\', '/', $base ),
        '/'
    );

    $remote_path =
        '/' === $base
            ? '/' . $relative_path
            : $base . '/' . $relative_path;

    if ( 'sftp' === $protocol ) {

        return swtch_delete_file_sftp(
            $host,
            $port,
            $username,
            $password,
            $remote_path
        );
    }

    return swtch_delete_file_ftp(
        $host,
        $port,
        $username,
        $password,
        $remote_path
    );
}
function swtch_delete_file_ftp(
    $host,
    $port,
    $username,
    $password,
    $remote_path
) {

    $connection = ftp_connect(
        $host,
        $port,
        30
    );

    if ( false === $connection ) {
        return new WP_Error(
            'swtch_ftp_connection_failed',
            'Could not connect to the FTP server.'
        );
    }

    if ( ! ftp_login( $connection, $username, $password ) ) {

        ftp_close( $connection );

        return new WP_Error(
            'swtch_ftp_login_failed',
            'FTP login failed.'
        );
    }

    /*
     * Already absent is effectively success.
     */
    if ( -1 === @ftp_size( $connection, $remote_path ) ) {

        ftp_close( $connection );

        return true;
    }

    $deleted = @ftp_delete(
        $connection,
        $remote_path
    );

    ftp_close( $connection );

    if ( ! $deleted ) {

        return new WP_Error(
            'swtch_ftp_delete_failed',
            'Could not delete remote file: ' . $remote_path
        );
    }

    return true;
}
function swtch_delete_file_sftp(
    $host,
    $port,
    $username,
    $password,
    $remote_path
) {

    if (
        ! function_exists( 'ssh2_connect' ) ||
        ! function_exists( 'ssh2_auth_password' ) ||
        ! function_exists( 'ssh2_sftp' )
    ) {
        return new WP_Error(
            'swtch_sftp_unavailable',
            'The PHP SSH2 extension is not available.'
        );
    }

    $connection = ssh2_connect(
        $host,
        $port
    );

    if ( false === $connection ) {
        return new WP_Error(
            'swtch_sftp_connection_failed',
            'Could not connect to the SFTP server.'
        );
    }

    if (
        ! ssh2_auth_password(
            $connection,
            $username,
            $password
        )
    ) {
        return new WP_Error(
            'swtch_sftp_login_failed',
            'SFTP login failed.'
        );
    }

    $sftp = ssh2_sftp( $connection );

    if ( false === $sftp ) {
        return new WP_Error(
            'swtch_sftp_init_failed',
            'Could not initialize SFTP.'
        );
    }

    $remote_uri =
        'ssh2.sftp://' .
        intval( $sftp ) .
        $remote_path;

    /*
     * Already absent is success.
     */
    if ( ! file_exists( $remote_uri ) ) {
        return true;
    }

    if (
        ! ssh2_sftp_unlink(
            $sftp,
            $remote_path
        )
    ) {
        return new WP_Error(
            'swtch_sftp_delete_failed',
            'Could not delete remote file: ' . $remote_path
        );
    }

    return true;
}

