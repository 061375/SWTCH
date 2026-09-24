<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Directory names that SWTCH must never recursively copy into an export.
 */
function swtch_get_local_excluded_names() {
    return [
        'node_modules',
        '.git',
        '.github',
        '.svn',
        '.idea',
        '.vscode',
    ];
}

/**
 * True when a local source path should be excluded from export processing.
 */
function swtch_is_local_path_ignored( $path ) {

    $path = wp_normalize_path( (string) $path );
    $path = untrailingslashit( $path );

    if ( '' === $path ) {
        return false;
    }

    $parts = array_filter( explode( '/', $path ), 'strlen' );

    foreach ( $parts as $part ) {
        if ( in_array( $part, swtch_get_local_excluded_names(), true ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Built-in remote protections. These are always ignored during deployment.
 */
function swtch_get_builtin_remote_ignore_rules() {
    return [
        '.htaccess',
        '.well-known/',
    ];
}

/**
 * Parse one-rule-per-line ignore text.
 * Blank lines and # comments are ignored.
 */
function swtch_parse_ignore_rules( $text ) {

    $rules = [];
    $lines = preg_split( '/\r\n|\r|\n/', (string) $text );

    foreach ( $lines as $line ) {
        $line = trim( $line );

        if ( '' === $line || str_starts_with( $line, '#' ) ) {
            continue;
        }

        $line = str_replace( '\\', '/', $line );
        $line = ltrim( $line, '/' );

        if ( '' !== $line ) {
            $rules[] = $line;
        }
    }

    return array_values( array_unique( $rules ) );
}

/**
 * Return built-in + user remote ignore rules.
 */
function swtch_get_remote_ignore_rules() {
    return array_values(
        array_unique(
            array_merge(
                swtch_get_builtin_remote_ignore_rules(),
                swtch_parse_ignore_rules( swtch_get_remote_ignore_rules_text() )
            )
        )
    );
}

/**
 * Normalize a deployment-relative path for ignore matching.
 */
function swtch_normalize_ignore_path( $path ) {
    $path = str_replace( '\\', '/', (string) $path );
    $path = preg_replace( '#/+#', '/', $path );
    return ltrim( $path, '/' );
}

/**
 * Match one path against one ignore rule.
 *
 * Rules ending in / ignore that directory recursively.
 * Wildcards use fnmatch(). A wildcard rule without a slash also matches
 * the basename, so *.log applies anywhere in the deployment tree.
 */
function swtch_path_matches_ignore_rule( $path, $rule ) {

    $path = swtch_normalize_ignore_path( $path );
    $rule = swtch_normalize_ignore_path( trim( (string) $rule ) );

    if ( '' === $path || '' === $rule ) {
        return false;
    }

    $directory_rule = str_ends_with( $rule, '/' );

    if ( $directory_rule ) {
        $directory = rtrim( $rule, '/' );

        if ( $path === $directory || str_starts_with( $path, $directory . '/' ) ) {
            return true;
        }

        return false;
    }

    $has_wildcard = strpbrk( $rule, '*?[' ) !== false;

    if ( $has_wildcard ) {
        if ( fnmatch( $rule, $path ) ) {
            return true;
        }

        if ( false === strpos( $rule, '/' ) && fnmatch( $rule, basename( $path ) ) ) {
            return true;
        }

        return false;
    }

    if ( false === strpos( $rule, '/' ) ) {
        return basename( $path ) === $rule;
    }

    return $path === $rule;
}

/**
 * True when a remote deployment path is protected by an ignore rule.
 */
function swtch_is_remote_path_ignored( $path ) {
    foreach ( swtch_get_remote_ignore_rules() as $rule ) {
        if ( swtch_path_matches_ignore_rule( $path, $rule ) ) {
            return true;
        }
    }

    return false;
}
