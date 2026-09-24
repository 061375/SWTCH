<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function swtch_export_wordpress_sitemap($exported_urls) {

    $export_root = WP_CONTENT_DIR . '/swtch-export';


    /*
    * Build a lookup table containing only the URLs
    * SWTCH actually exported.
    */
    $allowed_urls = [];

    foreach ( $exported_urls as $url ) {

        /*
        * Normalize the URL to its path.
        *
        * This allows us to compare the local WordPress URL
        * with the equivalent live-site sitemap URL.
        */
        $path = wp_parse_url(
            $url,
            PHP_URL_PATH
        );

        if ( false === $path || null === $path ) {
            continue;
        }

        $path = trailingslashit( $path );

        $allowed_urls[ $path ] = true;
    }


    $index_url = home_url( '/wp-sitemap.xml' );

    /*
     * Fetch WordPress sitemap index.
     */
    $response = wp_remote_get(
        $index_url,
        [
            'timeout' => 30,
        ]
    );

    if ( is_wp_error( $response ) ) {
        return false;
    }

    if (
        200 !== wp_remote_retrieve_response_code(
            $response
        )
    ) {
        return false;
    }

    $index_xml = wp_remote_retrieve_body(
        $response
    );

    if ( empty( $index_xml ) ) {
        return false;
    }

    /*
     * Parse sitemap index.
     */
    $index_dom = new DOMDocument();

    libxml_use_internal_errors( true );

    $loaded = $index_dom->loadXML(
        $index_xml
    );

    libxml_clear_errors();

    if ( ! $loaded ) {
        return false;
    }

    $xpath = new DOMXPath(
        $index_dom
    );

    /*
     * Find all child sitemap URLs.
     *
     * local-name() avoids XML namespace issues.
     */
    $sitemap_nodes = $xpath->query(
        '/*[local-name()="sitemapindex"]' .
        '/*[local-name()="sitemap"]' .
        '/*[local-name()="loc"]'
    );

    if (
        false === $sitemap_nodes ||
        0 === $sitemap_nodes->length
    ) {
        return false;
    }

    /*
     * Create final combined sitemap.
     */
    $output = new DOMDocument(
        '1.0',
        'UTF-8'
    );

    $output->formatOutput = true;

    $urlset = $output->createElement(
        'urlset'
    );

    $urlset->setAttribute(
        'xmlns',
        'http://www.sitemaps.org/schemas/sitemap/0.9'
    );

    $output->appendChild(
        $urlset
    );

    /*
     * Process each WordPress child sitemap.
     */
    foreach ( $sitemap_nodes as $sitemap_node ) {

        $child_url = trim(
            $sitemap_node->nodeValue
        );

        if ( empty( $child_url ) ) {
            continue;
        }

        $child_response = wp_remote_get(
            $child_url,
            [
                'timeout' => 30,
            ]
        );

        if ( is_wp_error( $child_response ) ) {
            continue;
        }

        if (
            200 !== wp_remote_retrieve_response_code(
                $child_response
            )
        ) {
            continue;
        }

        $child_xml = wp_remote_retrieve_body(
            $child_response
        );

        if ( empty( $child_xml ) ) {
            continue;
        }

        $child_dom = new DOMDocument();

        libxml_use_internal_errors( true );

        $loaded = $child_dom->loadXML(
            $child_xml
        );

        libxml_clear_errors();

        if ( ! $loaded ) {
            continue;
        }

        $child_xpath = new DOMXPath(
            $child_dom
        );

        $url_nodes = $child_xpath->query(
            '/*[local-name()="urlset"]' .
            '/*[local-name()="url"]'
        );

        if ( false === $url_nodes ) {
            continue;
        }

        foreach ( $url_nodes as $url_node ) {

            $loc_nodes = $child_xpath->query(
                './*[local-name()="loc"]',
                $url_node
            );

            if (
                false === $loc_nodes ||
                0 === $loc_nodes->length
            ) {
                continue;
            }

            $loc = trim(
                $loc_nodes->item( 0 )->nodeValue
            );

            if ( empty( $loc ) ) {
                continue;
            }

            /*
            * Get this sitemap URL's path.
            */
            $loc_path = wp_parse_url(
                $loc,
                PHP_URL_PATH
            );

            if (
                false === $loc_path ||
                null === $loc_path
            ) {
                continue;
            }

            $loc_path = trailingslashit(
                $loc_path
            );

            /*
            * Skip anything SWTCH did not export.
            *
            * Examples:
            *
            * /category/uncategorized/
            * /author/jeremy/
            */
            if ( ! isset( $allowed_urls[ $loc_path ] ) ) {
                continue;
            }

            /*
            * Convert the WordPress URL to the
            * configured live-site URL.
            */
            $loc = swtch_convert_to_live_url(
                $loc
            );

            $new_url = $output->createElement(
                'url'
            );

            $new_loc = $output->createElement(
                'loc'
            );

            $new_loc->appendChild(
                $output->createTextNode(
                    $loc
                )
            );

            $new_url->appendChild(
                $new_loc
            );

            /*
             * Preserve lastmod when WordPress provides it.
             */
            $lastmod_nodes = $child_xpath->query(
                './*[local-name()="lastmod"]',
                $url_node
            );

            if (
                false !== $lastmod_nodes &&
                $lastmod_nodes->length > 0
            ) {

                $lastmod = trim(
                    $lastmod_nodes->item( 0 )->nodeValue
                );

                if ( ! empty( $lastmod ) ) {

                    $new_lastmod =
                        $output->createElement(
                            'lastmod'
                        );

                    $new_lastmod->appendChild(
                        $output->createTextNode(
                            $lastmod
                        )
                    );

                    $new_url->appendChild(
                        $new_lastmod
                    );
                }
            }

            $urlset->appendChild(
                $new_url
            );
        }
    }

    /*
     * Save as the conventional sitemap.xml.
     */
    return $output->save(
        $export_root . '/sitemap.xml'
    );
}
