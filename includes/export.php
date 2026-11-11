<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function swtch_get_export_path( $url ) {

    $export_root = WP_CONTENT_DIR . '/swtch-export';

    $path = wp_parse_url( $url, PHP_URL_PATH );

    $path = trim( $path, '/' );

    /*
     * Homepage.
     */
    if ( empty( $path ) ) {
        return $export_root . '/index.html';
    }

    /*
     * Example:
     *
     * /sample-page/
     *
     * becomes:
     *
     * /swtch-export/sample-page/index.html
     */
    return $export_root . '/' . $path . '/index.html';
}
function swtch_generate_static_page( $url ) {

    $response = wp_remote_get(
        $url,
        [
            'timeout' => 30,
        ]
    );

    /*
     * Request failed.
     */
    if ( is_wp_error( $response ) ) {

        return [
            'success' => false,
            'message' => $response->get_error_message(),
        ];
    }

    /*
     * Check HTTP response code.
     */
    $status_code = wp_remote_retrieve_response_code( $response );

    if ( 200 !== $status_code ) {

        return [
            'success' => false,
            'message' => 'HTTP status ' . $status_code,
        ];
    }

    /*
     * Get rendered HTML.
     */
    $html = wp_remote_retrieve_body( $response );

    /*
    * Rewrite WordPress paths first.
    */
    $html = swtch_rewrite_internal_urls( $html );

    /*
    * Find and copy files referenced by this HTML.
    */
    swtch_process_html_assets( $html );

    $warnings = [];
    $optimized = swtch_bundle_javascript( $html );
    if ( is_wp_error( $optimized ) ) {
        // Optimization is optional: always retain the complete original page.
        $warnings[] = $optimized->get_error_message() . ' Original scripts retained.';
    } else {
        $html = $optimized;
    }

    /*
     * Determine where this page should be saved.
     */
    $file_path = swtch_get_export_path( $url );

    $directory = dirname( $file_path );

    /*
     * Create directories if they do not already exist.
     */
    if ( ! wp_mkdir_p( $directory ) ) {

        return [
            'success' => false,
            'message' => 'Could not create directory: ' . $directory,
        ];
    }

    /*
     * Save HTML.
     */
    $result = file_put_contents( $file_path, $html );

    if ( false === $result ) {

        return [
            'success' => false,
            'message' => 'Could not write file: ' . $file_path,
        ];
    }

    return [
        'success' => true,
        'message' => $file_path,
        'warnings' => $warnings,
    ];
}

function swtch_convert_to_live_url( $url ) {

    $live_site_url = swtch_get_live_site_url();

    /*
     * No live URL configured.
     * Leave the URL unchanged.
     */
    if ( empty( $live_site_url ) ) {
        return $url;
    }

    $local_site_url = untrailingslashit(
        home_url()
    );

    return str_replace(
        $local_site_url,
        $live_site_url,
        $url
    );
}
function swtch_rewrite_json_ld_urls( $html ) {
    $live_url = swtch_get_live_site_url();
    if ( '' === $live_url ) {
        return $html;
    }

    $local_base = preg_replace( '~^https?://~i', '', untrailingslashit( home_url() ) );
    $live_base = preg_replace( '~^https?://~i', '', $live_url );
    $live_host = preg_replace( '/^www\./i', '', $live_base );
    $url_pattern = '~^https?://(?:' . preg_quote( $local_base, '~' ) . '|(?:www\.)?' .
        preg_quote( $live_host, '~' ) . ')(?=[/?#]|$)~i';

    $rewrite = function ( &$value ) use ( &$rewrite, $url_pattern, $live_url ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            foreach ( $value as &$child ) {
                $rewrite( $child );
            }
        } elseif ( is_string( $value ) && preg_match( $url_pattern, $value, $match ) ) {
            $value = $live_url . substr( $value, strlen( $match[0] ) );
        }
    };

    return preg_replace_callback(
        '~(<script\b[^>]*>)(.*?)(</script\s*>)~is',
        function ( $matches ) use ( $rewrite ) {
            if ( ! preg_match( '~\s+type\s*=\s*(?:"application/ld\+json"|\'application/ld\+json\'|application/ld\+json(?=\s|>))~i', $matches[1] ) ) {
                return $matches[0];
            }

            $data = json_decode( $matches[2] );
            if ( JSON_ERROR_NONE !== json_last_error() ) {
                return $matches[0];
            }

            $rewrite( $data );
            $json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG );
            return false === $json ? $matches[0] : $matches[1] . $json . $matches[3];
        },
        $html
    );
}

function swtch_rewrite_absolute_metadata_urls( $html ) {

    $html = swtch_rewrite_json_ld_urls( $html );

    /*
     * <link rel="canonical" href="...">
     */
    $html = preg_replace_callback(
        '/<link\b[^>]*>/i',
        function ( $matches ) {

            $tag = $matches[0];

            if (
                ! preg_match(
                    '/\brel=["\'][^"\']*canonical[^"\']*["\']/i',
                    $tag
                )
            ) {
                return $tag;
            }

            return preg_replace_callback(
                '/\bhref=(["\'])(.*?)\1/i',
                function ( $href_matches ) {

                    $url = swtch_convert_to_live_url(
                        $href_matches[2]
                    );

                    return 'href=' .
                        $href_matches[1] .
                        esc_url( $url ) .
                        $href_matches[1];
                },
                $tag
            );
        },
        $html
    );

    /*
     * Open Graph and Twitter metadata.
     *
     * <meta property="og:url" content="...">
     * <meta name="twitter:url" content="...">
     */
    $html = preg_replace_callback(
        '/<meta\b[^>]*>/i',
        function ( $matches ) {

            $tag = $matches[0];

            $is_absolute_url_metadata =
                preg_match(
                    '/\bproperty=["\']og:url["\']/i',
                    $tag
                ) ||
                preg_match(
                    '/\bname=["\']twitter:url["\']/i',
                    $tag
                );

            if ( ! $is_absolute_url_metadata ) {
                return $tag;
            }

            return preg_replace_callback(
                '/\bcontent=(["\'])(.*?)\1/i',
                function ( $content_matches ) {

                    $url = swtch_convert_to_live_url(
                        $content_matches[2]
                    );

                    return 'content=' .
                        $content_matches[1] .
                        esc_url( $url ) .
                        $content_matches[1];
                },
                $tag
            );
        },
        $html
    );

    return $html;
}

/**
 * Remove the previous static export before starting a new build.
 */
function swtch_clear_export_directory() {

    $export_root = WP_CONTENT_DIR . '/swtch-export';

    if ( ! is_dir( $export_root ) ) {
        return true;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $export_root,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ( $iterator as $item ) {

        $path = $item->getPathname();

        if ( $item->isDir() ) {

            if ( ! rmdir( $path ) ) {
                return false;
            }

        } else {

            if ( ! unlink( $path ) ) {
                return false;
            }
        }
    }

    return true;
}