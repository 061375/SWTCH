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
function swtch_rewrite_absolute_metadata_urls( $html ) {

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