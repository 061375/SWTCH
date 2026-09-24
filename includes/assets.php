<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function swtch_rewrite_internal_urls( $html ) {

    $site_url   = untrailingslashit( home_url() );
    $asset_base = swtch_get_asset_base();

    /*
     * Some metadata requires absolute URLs.
     *
     * Do this BEFORE stripping the development hostname.
     */
    $html = swtch_rewrite_absolute_metadata_urls(
        $html
    );

    /*
     * Remove development hostname from normal internal URLs.
     */
    $html = str_replace(
        [
            $site_url . '/',
            $site_url,
        ],
        [
            '/',
            '/',
        ],
        $html
    );

    /*
     * Rewrite wp-content URLs.
     */
    $html = str_replace(
        '/wp-content/',
        '/' . $asset_base . '/',
        $html
    );

    /*
     * Rewrite wp-includes URLs.
     */
    $html = str_replace(
        '/wp-includes/',
        '/core/',
        $html
    );

    return $html;
}
function swtch_copy_core_assets() {

    $export_root = WP_CONTENT_DIR . '/swtch-export';

    $directories = [
        'css',
        'js',
        'images',
        'fonts',
    ];

    foreach ( $directories as $directory ) {

        $source = ABSPATH . WPINC . '/' . $directory;

        $destination =
            $export_root .
            '/core/' .
            $directory;

        if ( is_dir( $source ) ) {

            swtch_copy_directory(
                $source,
                $destination
            );
        }
    }
}
/**
/**
 * Copy a directory recursively while honoring SWTCH local exclusions.
 */
function swtch_copy_directory( $source, $destination ) {

    if ( ! is_dir( $source ) ) {
        return false;
    }

    if ( swtch_is_local_path_ignored( $source ) ) {
        return true;
    }

    if ( ! is_dir( $destination ) ) {
        wp_mkdir_p( $destination );
    }

    $items = scandir( $source );

    if ( false === $items ) {
        return false;
    }

    foreach ( $items as $item ) {

        if ( '.' === $item || '..' === $item ) {
            continue;
        }

        $source_path = $source . '/' . $item;
        $dest_path   = $destination . '/' . $item;

        if ( swtch_is_local_path_ignored( $source_path ) ) {
            continue;
        }

        if ( is_dir( $source_path ) ) {
            swtch_copy_directory( $source_path, $dest_path );
        } else {
            copy( $source_path, $dest_path );
        }
    }

    return true;
}
function swtch_normalize_url_path( $path ) {

    $segments = explode( '/', $path );
    $resolved = [];

    foreach ( $segments as $segment ) {

        if ( '' === $segment || '.' === $segment ) {
            continue;
        }

        if ( '..' === $segment ) {
            array_pop( $resolved );
            continue;
        }

        $resolved[] = $segment;
    }

    return '/' . implode( '/', $resolved );
}

/**
 * Resolve a CSS asset URL relative to the stylesheet URL.
 */
/**
 * Resolve a CSS asset reference relative to its stylesheet URL.
 */
function swtch_resolve_css_asset_url( $css_url, $asset_url ) {

    $asset_url = trim( $asset_url );

    /*
     * Ignore empty references.
     */
    if ( empty( $asset_url ) ) {
        return false;
    }

    /*
     * Ignore inline data URLs.
     *
     * Example:
     *
     * data:image/svg+xml;base64,...
     */
    if ( str_starts_with( $asset_url, 'data:' ) ) {
        return false;
    }

    /*
     * Ignore anchors.
     */
    if ( str_starts_with( $asset_url, '#' ) ) {
        return false;
    }

    /*
     * Ignore remote URLs.
     */
    if (
        str_starts_with( $asset_url, 'http://' ) ||
        str_starts_with( $asset_url, 'https://' ) ||
        str_starts_with( $asset_url, '//' )
    ) {
        return false;
    }

    /*
     * Root-relative URL.
     *
     * Example:
     *
     * /assets/uploads/image.jpg
     */
    if ( str_starts_with( $asset_url, '/' ) ) {
        return $asset_url;
    }

    /*
     * Relative URL.
     *
     * Example:
     *
     * CSS:
     * /assets/themes/jeremy2026/dist/app.css
     *
     * Reference:
     * ../images/header.jpg
     */
    $css_path = wp_parse_url(
        $css_url,
        PHP_URL_PATH
    );

    if ( ! $css_path ) {
        return false;
    }

    $base_directory = dirname( $css_path );

    $combined =
        $base_directory .
        '/' .
        $asset_url;

    /*
     * Normalize:
     *
     * /assets/css/../images/test.jpg
     *
     * becomes:
     *
     * /assets/images/test.jpg
     */
    $parts = explode(
        '/',
        $combined
    );

    $normalized = [];

    foreach ( $parts as $part ) {

        if (
            '' === $part ||
            '.' === $part
        ) {
            continue;
        }

        if ( '..' === $part ) {

            array_pop( $normalized );

            continue;
        }

        $normalized[] = $part;
    }

    return '/' . implode(
        '/',
        $normalized
    );
}

/**
 * Find assets referenced by url(...) inside a CSS file.
 */
/**
 * Find asset references inside a CSS file.
 */
function swtch_find_css_assets( $css ) {

    $assets = [];

    /*
     * url(...)
     *
     * Examples:
     *
     * url("../images/test.jpg")
     * url('../fonts/font.woff2')
     * url(/assets/uploads/image.png)
     */
    preg_match_all(
        '/url\(\s*[\'"]?([^\'"\)]+)[\'"]?\s*\)/i',
        $css,
        $matches
    );

    if ( ! empty( $matches[1] ) ) {
        $assets = array_merge(
            $assets,
            $matches[1]
        );
    }

    /*
     * @import
     *
     * Examples:
     *
     * @import "another.css";
     * @import url("another.css");
     */
    preg_match_all(
        '/@import\s+(?:url\()?[\s]*[\'"]([^\'"]+)[\'"]/i',
        $css,
        $matches
    );

    if ( ! empty( $matches[1] ) ) {
        $assets = array_merge(
            $assets,
            $matches[1]
        );
    }

    return array_unique( $assets );
}
function swtch_copy_asset( $url ) {

    static $processed = [];

    /*
     * Normalize URL so the same asset is not processed repeatedly.
     */
    $url_path = wp_parse_url(
        $url,
        PHP_URL_PATH
    );

    if ( ! $url_path ) {
        return false;
    }

    /*
     * Prevent recursive loops.
     *
     * Example:
     *
     * app.css imports other.css
     * other.css imports app.css
     */
    if ( isset( $processed[ $url_path ] ) ) {
        return true;
    }

    $processed[ $url_path ] = true;

    $source =
        swtch_asset_url_to_source_path(
            $url
        );

    $destination =
        swtch_asset_url_to_export_path(
            $url
        );

    if (
        ! $source ||
        ! $destination
    ) {
        return false;
    }

    if ( swtch_is_local_path_ignored( $source ) ) {
        return false;
    }

    if ( ! file_exists( $source ) ) {
        return false;
    }

    $directory = dirname(
        $destination
    );

    if ( ! wp_mkdir_p( $directory ) ) {
        return false;
    }

    /*
     * Copy the asset.
     */
    if (
        ! copy(
            $source,
            $destination
        )
    ) {
        return false;
    }

    /*
     * If this is a CSS file, inspect it for
     * additional dependencies.
     */
    $extension = strtolower(
        pathinfo(
            $source,
            PATHINFO_EXTENSION
        )
    );

    if ( 'css' === $extension ) {

        swtch_process_css_assets(
            $url,
            $source
        );
    }

    return true;
}
function swtch_find_html_assets( $html ) {

    $assets = [];

    $dom = new DOMDocument();

    /*
     * Suppress warnings caused by imperfect HTML5 markup.
     */
    libxml_use_internal_errors( true );

    $dom->loadHTML( $html );

    libxml_clear_errors();

    /*
     * <script src="">
     */
    foreach ( $dom->getElementsByTagName( 'script' ) as $element ) {

        $src = $element->getAttribute( 'src' );

        if ( $src ) {
            $assets[] = $src;
        }
    }

    /*
     * <link href="">
     *
     * This will catch stylesheets and similar linked resources.
     */
    foreach ( $dom->getElementsByTagName( 'link' ) as $element ) {

        $href = $element->getAttribute( 'href' );

        if ( $href ) {
            $assets[] = $href;
        }
    }

    /*
     * <img src="">
     */
    foreach ( $dom->getElementsByTagName( 'img' ) as $element ) {

        $src = $element->getAttribute( 'src' );

        if ( $src ) {
            $assets[] = $src;
        }
    }

    /*
    * <img srcset="">
    */
    foreach ( $dom->getElementsByTagName( 'img' ) as $element ) {

        $srcset = $element->getAttribute( 'srcset' );

        if ( ! $srcset ) {
            continue;
        }

        $srcset_assets =
            swtch_find_srcset_assets(
                $srcset
            );

        $assets = array_merge(
            $assets,
            $srcset_assets
        );
    }
    /*
    * <source src="">
    */
    foreach ( $dom->getElementsByTagName( 'source' ) as $element ) {

        $src = $element->getAttribute( 'src' );

        if ( $src ) {
            $assets[] = $src;
        }
    }

    /*
    * <source srcset="">
    */
    foreach ( $dom->getElementsByTagName( 'source' ) as $element ) {

        $srcset = $element->getAttribute( 'srcset' );

        if ( ! $srcset ) {
            continue;
        }

        $srcset_assets =
            swtch_find_srcset_assets(
                $srcset
            );

        $assets = array_merge(
            $assets,
            $srcset_assets
        );
    }
    /*
    * <video src="">
    */
    foreach ( $dom->getElementsByTagName( 'video' ) as $element ) {

        $src = $element->getAttribute( 'src' );

        if ( $src ) {
            $assets[] = $src;
        }

        $poster = $element->getAttribute( 'poster' );

        if ( $poster ) {
            $assets[] = $poster;
        }
    }

    /*
    * <audio src="">
    */
    foreach ( $dom->getElementsByTagName( 'audio' ) as $element ) {

        $src = $element->getAttribute( 'src' );

        if ( $src ) {
            $assets[] = $src;
        }
    }

    return array_unique( $assets );
}
/**
 * Convert an exported asset URL back into its local WordPress source file.
 */
function swtch_asset_url_to_source_path( $url ) {

    $asset_base = swtch_get_asset_base();

    /*
     * Ignore query strings.
     */
    $path = wp_parse_url( $url, PHP_URL_PATH );

    if ( ! $path ) {
        return false;
    }

    /*
     * /assets/... maps back to /wp-content/...
     */
    $asset_prefix = '/' . $asset_base . '/';

    if ( str_starts_with( $path, $asset_prefix ) ) {

        $relative_path = substr(
            $path,
            strlen( $asset_prefix )
        );

        return WP_CONTENT_DIR . '/' . $relative_path;
    }

    /*
     * /core/... maps back to /wp-includes/...
     */
    if ( str_starts_with( $path, '/core/' ) ) {

        $relative_path = substr(
            $path,
            strlen( '/core/' )
        );

        return ABSPATH . WPINC . '/' . $relative_path;
    }

    return false;
}
/**
 * Determine where an asset should be saved in the export.
 */
function swtch_asset_url_to_export_path( $url ) {

    $export_root = WP_CONTENT_DIR . '/swtch-export';

    $path = wp_parse_url( $url, PHP_URL_PATH );

    if ( ! $path ) {
        return false;
    }

    return $export_root . '/' . ltrim( $path, '/' );
}
/**
 * Find and copy assets referenced by generated HTML.
 */
function swtch_process_html_assets( $html ) {

    $assets = swtch_find_html_assets( $html );

    foreach ( $assets as $asset ) {

        swtch_copy_asset( $asset );
    }
}
/**
 * Find and copy assets referenced by a CSS file.
 */
function swtch_process_css_assets( $css_url, $css_file ) {

    if ( ! file_exists( $css_file ) ) {
        return;
    }

    $css = file_get_contents( $css_file );

    if ( false === $css ) {
        return;
    }

    $assets = swtch_find_css_assets( $css );

    foreach ( $assets as $asset ) {

        $resolved_url =
            swtch_resolve_css_asset_url(
                $css_url,
                $asset
            );

        if ( ! $resolved_url ) {
            continue;
        }

        swtch_copy_asset(
            $resolved_url
        );
    }
}
/**
 * Extract URLs from a srcset attribute.
 *
 * Example:
 *
 * image-300.jpg 300w,
 * image-768.jpg 768w,
 * image-1200.jpg 1200w
 */
function swtch_find_srcset_assets( $srcset ) {

    $assets = [];

    $items = explode(
        ',',
        $srcset
    );

    foreach ( $items as $item ) {

        $item = trim( $item );

        if ( empty( $item ) ) {
            continue;
        }

        /*
         * URL is always the first value.
         *
         * Example:
         *
         * /assets/uploads/photo.jpg 768w
         */
        $parts = preg_split(
            '/\s+/',
            $item
        );

        if (
            ! empty( $parts ) &&
            ! empty( $parts[0] )
        ) {
            $assets[] = $parts[0];
        }
    }

    return $assets;
}
