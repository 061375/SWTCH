<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Option used to remember every permalink seen for each exported post/page.
 *
 * Stored shape:
 * [
 *     123 => [
 *         'https://local.test/about-me/',
 *         'https://local.test/about/',
 *     ],
 * ]
 */
function swtch_get_permalink_history() {

    $history = get_option( 'swtch_permalink_history', [] );

    return is_array( $history ) ? $history : [];
}

/**
 * Return the current permalink for every published page/post, keyed by post ID.
 */
function swtch_get_current_permalink_map() {

    $map = [];

    $posts = get_posts( [
        'post_type'      => [ 'page', 'post' ],
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ] );

    foreach ( $posts as $post ) {

        $permalink = get_permalink( $post->ID );

        if ( ! $permalink ) {
            continue;
        }

        $map[ (int) $post->ID ] = $permalink;
    }

    return $map;
}

/**
 * Normalize a URL to the path used by an Apache Redirect directive.
 */
function swtch_redirect_path_from_url( $url ) {

    $path = wp_parse_url( $url, PHP_URL_PATH );

    if ( false === $path || null === $path || '' === $path ) {
        return '/';
    }

    $path = '/' . ltrim( $path, '/' );

    if ( '/' !== $path ) {
        $path = trailingslashit( $path );
    }

    return $path;
}

/**
 * Refresh permalink history and return automatic redirects.
 *
 * Every historical URL for a post/page points directly at its current URL,
 * preventing chains such as old -> newer -> newest.
 */
function swtch_refresh_automatic_redirects() {

    $history = swtch_get_permalink_history();
    $current = swtch_get_current_permalink_map();

    /*
     * First record the current permalink for every published item.
     */
    foreach ( $current as $post_id => $current_url ) {

        if ( ! isset( $history[ $post_id ] ) || ! is_array( $history[ $post_id ] ) ) {
            $history[ $post_id ] = [];
        }

        if ( ! in_array( $current_url, $history[ $post_id ], true ) ) {
            $history[ $post_id ][] = $current_url;
        }
    }

    update_option( 'swtch_permalink_history', $history, false );

    /*
     * Build a lookup of paths that currently exist. A historical path must not
     * become a redirect source if WordPress is currently using it for another
     * published page/post.
     */
    $current_paths = [];

    foreach ( $current as $current_url ) {
        $current_paths[ swtch_redirect_path_from_url( $current_url ) ] = true;
    }

    $redirects = [];

    foreach ( $current as $post_id => $current_url ) {

        if ( empty( $history[ $post_id ] ) || ! is_array( $history[ $post_id ] ) ) {
            continue;
        }

        $target_path = swtch_redirect_path_from_url( $current_url );

        foreach ( $history[ $post_id ] as $historical_url ) {

            $source_path = swtch_redirect_path_from_url( $historical_url );

            if ( $source_path === $target_path ) {
                continue;
            }

            /*
             * Never redirect a path that is currently a real exported URL.
             */
            if ( isset( $current_paths[ $source_path ] ) ) {
                continue;
            }

            $redirects[ $source_path ] = $target_path;
        }
    }

    ksort( $redirects );

    return $redirects;
}

/**
 * Create the Apache block shown in the SWTCH Build Site results panel.
 */
function swtch_generate_redirect_block() {

    $redirects = swtch_refresh_automatic_redirects();

    $lines = [
        '# BEGIN SWTCH Redirects',
    ];

    foreach ( $redirects as $source => $target ) {
        $lines[] = sprintf(
            'Redirect 301 %s %s',
            $source,
            $target
        );
    }

    if ( empty( $redirects ) ) {
        $lines[] = '# No automatic redirects detected.';
    }

    $lines[] = '# END SWTCH Redirects';

    return [
        'count'     => count( $redirects ),
        'redirects' => $redirects,
        'block'     => implode( "\n", $lines ),
    ];
}
