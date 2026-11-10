<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Minify adjacent local classic scripts without moving execution across HTML,
 * inline configuration, or scripts with special loading semantics.
 * Work on offsets so DOMDocument never reserializes the exported page.
 */
function swtch_bundle_javascript( $html ) {
    // Return before checking dependencies or attempting to start Node.
    if ( ! swtch_javascript_optimization_enabled() ) {
        return $html;
    }
    $runner = SWTCH_PLUGIN_DIR . 'lib/javascript/minify.cjs';
    if ( ! is_file( dirname( $runner ) . '/node_modules/terser/package.json' ) || ! is_callable( 'proc_open' ) ) {
        return new WP_Error( 'swtch_minifier_missing', 'SWTCH JavaScript minifier is unavailable. Install its dependencies in lib/javascript and enable proc_open.' );
    }

    preg_match_all( '~<script\b[^>]*>.*?</script\s*>~is', $html, $matches, PREG_OFFSET_CAPTURE );
    $groups = [];
    $group = [];
    $end = null;
    foreach ( $matches[0] as $match ) {
        [ $tag, $offset ] = $match;
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors( true );
        $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $tag );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        $element = $dom->getElementsByTagName( 'script' )->item( 0 );
        $src = $element ? $element->getAttribute( 'src' ) : '';
        $type = $element ? strtolower( trim( $element->getAttribute( 'type' ) ) ) : '';
        $eligible = $src && in_array( $type, [ '', 'text/javascript', 'application/javascript' ], true );
        if ( $element ) {
            foreach ( $element->attributes as $attribute ) {
                // IDs and custom attributes may be observed by the script itself.
                if ( ! in_array( strtolower( $attribute->name ), [ 'src', 'type', 'defer', 'id', 'data-wp-strategy' ], true ) ) {
                    $eligible = false;
                }
            }
        }
        $path = wp_parse_url( $src, PHP_URL_PATH );
        $source = $eligible && ! wp_parse_url( $src, PHP_URL_HOST ) && str_starts_with( $src, '/' )
            ? swtch_asset_url_to_source_path( $src ) : false;
        $real = $source ? realpath( $source ) : false;
        $roots = [ realpath( WP_CONTENT_DIR ), realpath( ABSPATH . WPINC ) ];
        $inside = false;
        foreach ( $roots as $root ) {
            if ( $real && $root && str_starts_with( $real, $root . DIRECTORY_SEPARATOR ) ) { $inside = true; }
        }
        $eligible = $eligible && $inside && is_readable( $real ) && ! swtch_is_local_path_ignored( $real ) && strtolower( pathinfo( $path ?? '', PATHINFO_EXTENSION ) ) === 'js';
        $code = $eligible ? file_get_contents( $real ) : false;
        // Moving these scripts can change asset resolution or parser behaviour.
        if ( false === $code || preg_match( '~document\s*\.\s*(?:currentScript|write|writeln)|\bimport\s*\(|\bimport\s*\.\s*meta~', $code ) ) { $eligible = false; }
        $mode = $element && $element->hasAttribute( 'defer' ) ? 'defer' : 'blocking';
        $signature = $mode;
        // A directive must not make neighbouring scripts strict accidentally.
        if ( preg_match( '~([\x22\x27])use strict\1~', $code ?: '' ) ) { $signature .= ':strict:' . $offset; }
        if ( $element && $element->hasAttribute( 'id' ) ) { $signature .= ':' . $offset; }
        if ( $group && ( ! $eligible || $signature !== $group[0]['signature'] || trim( substr( $html, $end, $offset - $end ) ) !== '' ) ) {
            $groups[] = $group;
            $group = [];
        }
        if ( $eligible ) {
            $group[] = compact( 'tag', 'offset', 'code', 'mode', 'signature' );
            $end = $offset + strlen( $tag );
        }
    }
    if ( $group ) { $groups[] = $group; }

    foreach ( array_reverse( $groups ) as $group ) {
        $input = wp_json_encode( array_column( $group, 'code' ) );
        $hash = hash( 'sha256', 'swtch-js-v1:' . $input );
        $url = '/' . swtch_get_asset_base() . '/swtch-js/' . $hash . '.min.js';
        $destination = swtch_asset_url_to_export_path( $url );
        if ( ! is_file( $destination ) ) {
            $input_file = tempnam( get_temp_dir(), 'swtch-js-' );
            if ( ! $input_file || false === file_put_contents( $input_file, $input ) ) { return new WP_Error( 'swtch_minifier_input', 'Could not prepare JavaScript minifier input.' ); }
            $pipes = [];
            $process = proc_open( [ apply_filters( 'swtch_node_binary', 'node' ), '--jitless', $runner ], [ 0 => [ 'file', $input_file, 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'file', '/dev/null', 'a' ] ], $pipes );
            if ( ! is_resource( $process ) ) { unlink( $input_file ); return new WP_Error( 'swtch_minifier_start', 'Could not start the SWTCH JavaScript minifier.' ); }
            $output = stream_get_contents( $pipes[1] );
            fclose( $pipes[1] );
            $status = proc_close( $process );
            unlink( $input_file );
            if ( 0 !== $status ) { return new WP_Error( 'swtch_minifier_failed', 'JavaScript minification failed; original page was not replaced.' ); }
            if ( ! wp_mkdir_p( dirname( $destination ) ) || false === file_put_contents( $destination, $output, LOCK_EX ) ) {
                return new WP_Error( 'swtch_bundle_write', 'Could not write JavaScript bundle.' );
            }
        }
        $first = $group[0];
        $last = $group[ count( $group ) - 1 ];
        $replacement = '<script src="' . esc_attr( $url ) . '"' . ( 'defer' === $first['mode'] ? ' defer' : '' ) . '></script>';
        if ( 1 === count( $group ) ) {
            $replacement = preg_replace( '~\bsrc\s*=\s*(?:"[^"]*"|\x27[^\x27]*\x27|[^\s>]+)~i', 'src="' . esc_attr( $url ) . '"', $first['tag'], 1 );
        }
        $html = substr_replace( $html, $replacement, $first['offset'], $last['offset'] + strlen( $last['tag'] ) - $first['offset'] );
    }
    return $html;
}
