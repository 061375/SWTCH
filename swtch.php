<?php
/**
 * Plugin Name: SWTCH
 * Plugin URI:
 * Description: Static WordPress To Common HTML
 * Version: 0.1.0
 * Author: Jeremy Heminger
 * License: GPL-2.0-or-later
 * Text Domain: swtch
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register SWTCH admin menu.
 */
function swtch_add_admin_menu() {

    add_options_page(
        'SWTCH',
        'SWTCH',
        'manage_options',
        'swtch',
        'swtch_render_admin_page'
    );
}

add_action( 'admin_menu', 'swtch_add_admin_menu' );

/**
 * Convert a WordPress URL into its static export file path.
 */
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
/**
 * Rewrite internal WordPress URLs for static output.
 */
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
        $site_url,
        '',
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
 * Copy a directory recursively.
 */
function swtch_copy_directory( $source, $destination ) {

    if ( ! is_dir( $source ) ) {
        return false;
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

        if ( is_dir( $source_path ) ) {

            swtch_copy_directory(
                $source_path,
                $dest_path
            );

        } else {

            copy(
                $source_path,
                $dest_path
            );
        }
    }

    return true;
}
/**
 * Fetch a rendered WordPress page and save it as static HTML.
 */
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

/**
 * Render the SWTCH administration page.
 */
function swtch_render_admin_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $urls = [];
    $results = [];
    
    /*
    * Save SWTCH settings.
    */
    if ( isset( $_POST['swtch_save_settings'] ) ) {

        if (
            ! isset( $_POST['swtch_settings_nonce'] ) ||
            ! wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash( $_POST['swtch_settings_nonce'] )
                ),
                'swtch_save_settings'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        $live_site_url = '';

        if ( isset( $_POST['swtch_live_site_url'] ) ) {

            $live_site_url = esc_url_raw(
                wp_unslash(
                    $_POST['swtch_live_site_url']
                )
            );

            $live_site_url = untrailingslashit(
                $live_site_url
            );
        }

        update_option(
            'swtch_live_site_url',
            $live_site_url
        );

        $deployment_protocol = 'ftp';

        if ( isset( $_POST['swtch_deployment_protocol'] ) ) {

            $deployment_protocol = sanitize_key(
                wp_unslash(
                    $_POST['swtch_deployment_protocol']
                )
            );
        }

        if ( ! in_array( $deployment_protocol, [ 'ftp', 'sftp' ], true ) ) {
            $deployment_protocol = 'ftp';
        }

        $deployment_host = '';

        if ( isset( $_POST['swtch_deployment_host'] ) ) {

            $deployment_host = sanitize_text_field(
                wp_unslash(
                    $_POST['swtch_deployment_host']
                )
            );
        }

        $deployment_port =
            'sftp' === $deployment_protocol
                ? 22
                : 21;

        if ( isset( $_POST['swtch_deployment_port'] ) ) {

            $deployment_port = absint(
                $_POST['swtch_deployment_port']
            );
        }

        if ( $deployment_port < 1 || $deployment_port > 65535 ) {

            $deployment_port =
                'sftp' === $deployment_protocol
                    ? 22
                    : 21;
        }

        $deployment_username = '';

        if ( isset( $_POST['swtch_deployment_username'] ) ) {

            $deployment_username = sanitize_text_field(
                wp_unslash(
                    $_POST['swtch_deployment_username']
                )
            );
        }

        $deployment_remote_path = '/';

        if ( isset( $_POST['swtch_deployment_remote_path'] ) ) {

            $deployment_remote_path = sanitize_text_field(
                wp_unslash(
                    $_POST['swtch_deployment_remote_path']
                )
            );
        }

        if ( empty( $deployment_remote_path ) ) {
            $deployment_remote_path = '/';
        }

        update_option(
            'swtch_deployment_protocol',
            $deployment_protocol
        );

        update_option(
            'swtch_deployment_host',
            $deployment_host
        );

        update_option(
            'swtch_deployment_port',
            $deployment_port
        );

        update_option(
            'swtch_deployment_username',
            $deployment_username
        );

        update_option(
            'swtch_deployment_remote_path',
            $deployment_remote_path
        );

        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>SWTCH settings saved.</p>';
        echo '</div>';
    }
    /*
     * Handle Test Connection button.
     */
    if ( isset( $_POST['swtch_test_connection'] ) ) {

        if (
            ! isset( $_POST['swtch_test_connection_nonce'] ) ||
            ! wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['swtch_test_connection_nonce']
                    )
                ),
                'swtch_test_connection'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        $password = '';

        if ( isset( $_POST['swtch_deployment_password'] ) ) {

            $password = (string) wp_unslash(
                $_POST['swtch_deployment_password']
            );
        }

        $connection_result = swtch_test_deployment_connection(
            swtch_get_deployment_protocol(),
            swtch_get_deployment_host(),
            swtch_get_deployment_port(),
            swtch_get_deployment_username(),
            $password,
            swtch_get_deployment_remote_path()
        );

        $notice_class =
            $connection_result['success']
                ? 'notice-success'
                : 'notice-error';

        echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible">';
        echo '<p>' . esc_html( $connection_result['message'] ) . '</p>';
        echo '</div>';
    }

    /*
     * Handle Deploy Static Site button.
     */
    if ( isset( $_POST['swtch_deploy'] ) ) {

        if (
            ! isset( $_POST['swtch_deploy_nonce'] ) ||
            ! wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash( $_POST['swtch_deploy_nonce'] )
                ),
                'swtch_deploy_site'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        $password = '';

        if ( isset( $_POST['swtch_deploy_password'] ) ) {
            $password = (string) wp_unslash(
                $_POST['swtch_deploy_password']
            );
        }

        $deployment_result = swtch_deploy_static_site(
            swtch_get_deployment_protocol(),
            swtch_get_deployment_host(),
            swtch_get_deployment_port(),
            swtch_get_deployment_username(),
            $password,
            swtch_get_deployment_remote_path()
        );

        $notice_class =
            $deployment_result['success']
                ? 'notice-success'
                : 'notice-error';

        echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible">';
        echo '<p>' . esc_html( $deployment_result['message'] ) . '</p>';
        echo '</div>';
    }

    /*
     * Handle Generate Static Site button.
     */
    if ( isset( $_POST['swtch_generate'] ) ) {

        /*
         * Verify nonce.
         */
        if (
            ! isset( $_POST['swtch_nonce'] ) ||
            ! wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash( $_POST['swtch_nonce'] )
                ),
                'swtch_generate_site'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        /*
         * Add homepage.
         */
        // $urls[] = [
        //     'url'     => home_url( '/' ),
        //     'lastmod' => current_time( DATE_W3C, true ),
        // ];
        $urls[] = home_url( '/' );

        /*
         * Get published pages and posts.
         */
        $posts = get_posts( [
            'post_type'      => [ 'page', 'post' ],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ] );

        /*
         * Get the permalink for each item.
         */
        foreach ( $posts as $post ) {

            $permalink = get_permalink( $post->ID );

            // if ( $permalink ) {

            //     $urls[] = [
            //         'url'     => $permalink,
            //         'lastmod' => get_post_modified_time(
            //             DATE_W3C,
            //             true,
            //             $post
            //         ),
            //     ];
            // }
            if ( $permalink ) {
                $urls[] = $permalink;
            }
        }

        /*
        * Remove duplicates.
        */
        $urls = array_unique( $urls );

        /*
        * Generate static HTML files.
        */
        foreach ( $urls as $url ) {

            $results[ $url ] = swtch_generate_static_page( $url );
        }
        /*
        * Generate sitemap.xml from the same URLs.
        */
        //swtch_generate_sitemap( $urls );
        swtch_export_wordpress_sitemap($urls);
    }

    ?>

    <div class="wrap">

        <div
            style="
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 10px;
            "
        >

            <img
                src="<?php echo esc_url(
                    plugin_dir_url( __FILE__ ) . 'swtch-logo.png'
                ); ?>"
                alt="SWTCH"
                style="
                    width: 100px;
                    height: 100px;
                    object-fit: contain;
                    border-top-left-radius: 35px;
                    border-top-right-radius: 35px;
                    box-shadow: -2px -3px 2px 0px #000;
                "
            >

            <!--h1 style="margin: 0;">
                SWTCH
            </h1-->

        </div>

        <p>
            Static WordPress To Common HTML
        </p>

        <hr>

        <h2>Settings</h2>

        <form method="post">

            <?php
            wp_nonce_field(
                'swtch_save_settings',
                'swtch_settings_nonce'
            );

            $live_site_url          = swtch_get_live_site_url();
            $deployment_protocol    = swtch_get_deployment_protocol();
            $deployment_host        = swtch_get_deployment_host();
            $deployment_port        = swtch_get_deployment_port();
            $deployment_username    = swtch_get_deployment_username();
            $deployment_remote_path = swtch_get_deployment_remote_path();
            ?>

            <table class="form-table">

                <tr>

                    <th scope="row">
                        <label for="swtch_live_site_url">
                            Live Site URL
                        </label>
                    </th>

                    <td>

                        <input
                            type="url"
                            id="swtch_live_site_url"
                            name="swtch_live_site_url"
                            value="<?php echo esc_attr( $live_site_url ); ?>"
                            class="regular-text"
                            placeholder="https://example.com"
                        >

                        <p class="description">
                            The public URL where the exported static site will be hosted.
                        </p>

                    </td>

                </tr>

                <tr>

                    <th scope="row">
                        <label for="swtch_deployment_protocol">
                            Deployment Protocol
                        </label>
                    </th>

                    <td>

                        <select
                            id="swtch_deployment_protocol"
                            name="swtch_deployment_protocol"
                        >
                            <option
                                value="ftp"
                                <?php selected( $deployment_protocol, 'ftp' ); ?>
                            >
                                FTP
                            </option>

                            <option
                                value="sftp"
                                <?php selected( $deployment_protocol, 'sftp' ); ?>
                            >
                                SFTP (SSH)
                            </option>
                        </select>

                        <p class="description">
                            FTP normally uses port 21. SFTP normally uses port 22.
                        </p>

                    </td>

                </tr>

                <tr>

                    <th scope="row">
                        <label for="swtch_deployment_host">
                            Host
                        </label>
                    </th>

                    <td>
                        <input
                            type="text"
                            id="swtch_deployment_host"
                            name="swtch_deployment_host"
                            value="<?php echo esc_attr( $deployment_host ); ?>"
                            class="regular-text"
                            placeholder="ftp.example.com"
                        >
                    </td>

                </tr>

                <tr>

                    <th scope="row">
                        <label for="swtch_deployment_port">
                            Port
                        </label>
                    </th>

                    <td>
                        <input
                            type="number"
                            id="swtch_deployment_port"
                            name="swtch_deployment_port"
                            value="<?php echo esc_attr( $deployment_port ); ?>"
                            class="small-text"
                            min="1"
                            max="65535"
                        >
                    </td>

                </tr>

                <tr>

                    <th scope="row">
                        <label for="swtch_deployment_username">
                            Username
                        </label>
                    </th>

                    <td>
                        <input
                            type="text"
                            id="swtch_deployment_username"
                            name="swtch_deployment_username"
                            value="<?php echo esc_attr( $deployment_username ); ?>"
                            class="regular-text"
                            autocomplete="username"
                        >
                    </td>

                </tr>

                <tr>

                    <th scope="row">
                        <label for="swtch_deployment_remote_path">
                            Remote Path
                        </label>
                    </th>

                    <td>
                        <input
                            type="text"
                            id="swtch_deployment_remote_path"
                            name="swtch_deployment_remote_path"
                            value="<?php echo esc_attr( $deployment_remote_path ); ?>"
                            class="regular-text"
                            placeholder="/public_html/"
                        >

                        <p class="description">
                            Directory where the static site will eventually be uploaded.
                        </p>
                    </td>

                </tr>

            </table>

            <?php submit_button(
                'Save Settings',
                'secondary',
                'swtch_save_settings'
            ); ?>

        </form>

        <h3>Test Deployment Connection</h3>

        <p>
            Save the deployment settings above first, then enter the password to test the connection.
            The password is not saved.
        </p>

        <form method="post">

            <?php
            wp_nonce_field(
                'swtch_test_connection',
                'swtch_test_connection_nonce'
            );
            ?>

            <table class="form-table">

                <tr>

                    <th scope="row">
                        <label for="swtch_deployment_password">
                            Password
                        </label>
                    </th>

                    <td>
                        <input
                            type="password"
                            id="swtch_deployment_password"
                            name="swtch_deployment_password"
                            class="regular-text"
                            autocomplete="current-password"
                        >
                    </td>

                </tr>

            </table>

            <?php submit_button(
                'Test Connection',
                'secondary',
                'swtch_test_connection'
            ); ?>

        </form>

        <hr>


        <h2>Static Site Generator</h2>

        <p>
            Generate a static HTML version of this WordPress website.
        </p>

        <form method="post">

            <?php wp_nonce_field( 'swtch_generate_site', 'swtch_nonce' ); ?>

            <input
                type="submit"
                name="swtch_generate"
                class="button button-primary"
                value="Generate Static Site"
            >

        </form>

        <hr>

        <h2>Deploy Static Site</h2>

        <p>
            Upload the contents of
            <code>wp-content/swtch-export/</code>
            to the configured remote directory. Existing files with the same names
            will be overwritten. Remote files are not deleted.
        </p>

        <form method="post">

            <?php
            wp_nonce_field(
                'swtch_deploy_site',
                'swtch_deploy_nonce'
            );
            ?>

            <table class="form-table">

                <tr>
                    <th scope="row">
                        <label for="swtch_deploy_password">
                            Deployment Password
                        </label>
                    </th>

                    <td>
                        <input
                            type="password"
                            id="swtch_deploy_password"
                            name="swtch_deploy_password"
                            class="regular-text"
                            autocomplete="current-password"
                        >

                        <p class="description">
                            The password is used for this deployment only and is not saved.
                        </p>
                    </td>
                </tr>

            </table>

            <?php submit_button(
                'Deploy Static Site',
                'primary',
                'swtch_deploy'
            ); ?>

        </form>

        <?php if ( ! empty( $urls ) ) : ?>

            <hr>

            <h2>Pages Found</h2>

            <p>
                SWTCH found <?php echo esc_html( count( $urls ) ); ?> URLs.
            </p>

            <table class="widefat striped">

                <thead>
                    <tr>
                        <th>URL</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ( $urls as $url ) : ?>

                        <?php
                        //$url    = $item['url'];
                        $result = $results[ $url ] ?? null;
                        ?>

                        <tr>

                            <td>

                                <a
                                    href="<?php echo esc_url( $url ); ?>"
                                    target="_blank"
                                >
                                    <?php echo esc_html( $url ); ?>
                                </a>

                                <?php if ( $result ) : ?>

                                    <br>

                                    <?php if ( $result['success'] ) : ?>

                                        <span style="color: green;">
                                            Generated
                                        </span>

                                        <code>
                                            <?php echo esc_html( $result['message'] ); ?>
                                        </code>

                                    <?php else : ?>

                                        <span style="color: red;">
                                            Failed:
                                            <?php echo esc_html( $result['message'] ); ?>
                                        </span>

                                    <?php endif; ?>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

    <?php
}
/**
 * Normalize a URL path, resolving . and .. segments.
 */
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
/**
 * Copy a single referenced asset into the static export.
 */
/**
 * Copy a single referenced asset into the static export.
 */
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
 * Get the configured deployment protocol.
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
 * Extract local asset URLs from generated HTML.
 */
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
/**
 * Export the WordPress sitemap as one combined sitemap.xml.
 */
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
/**
 * Rewrite metadata URLs that should remain absolute.
 *
 * Examples:
 * canonical
 * og:url
 * twitter:url
 */
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

/**
 * Deploy the generated static site using the configured transport.
 */
function swtch_deploy_static_site(
    $protocol,
    $host,
    $port,
    $username,
    $password,
    $remote_path
) {

    $export_root = WP_CONTENT_DIR . '/swtch-export';

    if ( ! is_dir( $export_root ) ) {

        return [
            'success' => false,
            'message' => 'No static export was found. Generate the static site first.',
        ];
    }

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

        return swtch_deploy_sftp(
            $host,
            $port,
            $username,
            $password,
            $remote_path,
            $export_root
        );
    }

    return swtch_deploy_ftp(
        $host,
        $port,
        $username,
        $password,
        $remote_path,
        $export_root
    );
}

/**
 * Deploy a directory tree using FTP.
 */
function swtch_deploy_ftp(
    $host,
    $port,
    $username,
    $password,
    $remote_path,
    $local_root
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
        20
    );

    if ( ! $connection ) {

        return [
            'success' => false,
            'message' => 'Could not connect to the FTP server.',
        ];
    }

    if ( ! @ftp_login( $connection, $username, $password ) ) {

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

    $stats = [
        'files'       => 0,
        'directories' => 0,
    ];

    $result = swtch_ftp_upload_directory(
        $connection,
        $local_root,
        '',
        $stats
    );

    ftp_close( $connection );

    if ( true !== $result ) {
        return $result;
    }

    return [
        'success' => true,
        'message' => sprintf(
            'Deployment complete: %d files uploaded and %d directories created or verified.',
            $stats['files'],
            $stats['directories']
        ),
    ];
}

/**
 * Recursively upload a local directory over FTP.
 */
function swtch_ftp_upload_directory(
    $connection,
    $local_directory,
    $remote_relative_path,
    &$stats
) {

    $items = scandir( $local_directory );

    if ( false === $items ) {

        return [
            'success' => false,
            'message' => 'Could not read local export directory: ' . $local_directory,
        ];
    }

    foreach ( $items as $item ) {

        if ( '.' === $item || '..' === $item ) {
            continue;
        }

        $local_path = $local_directory . '/' . $item;

        $remote_path = ltrim(
            $remote_relative_path . '/' . $item,
            '/'
        );

        if ( is_dir( $local_path ) ) {

            if ( ! swtch_ftp_ensure_directory( $connection, $remote_path ) ) {

                return [
                    'success' => false,
                    'message' => 'Could not create remote FTP directory: ' . $remote_path,
                ];
            }

            $stats['directories']++;

            $result = swtch_ftp_upload_directory(
                $connection,
                $local_path,
                $remote_path,
                $stats
            );

            if ( true !== $result ) {
                return $result;
            }

            continue;
        }

        if (
            ! @ftp_put(
                $connection,
                $remote_path,
                $local_path,
                FTP_BINARY
            )
        ) {

            return [
                'success' => false,
                'message' => 'Could not upload file by FTP: ' . $remote_path,
            ];
        }

        $stats['files']++;
    }

    return true;
}

/**
 * Ensure a nested FTP directory exists relative to the current remote root.
 */
function swtch_ftp_ensure_directory( $connection, $remote_path ) {

    $base_directory = @ftp_pwd( $connection );

    if ( false === $base_directory ) {
        return false;
    }

    $parts = array_filter(
        explode( '/', trim( $remote_path, '/' ) ),
        'strlen'
    );

    $current = '';

    foreach ( $parts as $part ) {

        $current = empty( $current )
            ? $part
            : $current . '/' . $part;

        /*
         * ftp_chdir() is used only to test whether the directory exists.
         * Always return to the configured deployment root afterward.
         */
        if ( @ftp_chdir( $connection, $current ) ) {
            @ftp_chdir( $connection, $base_directory );
            continue;
        }

        @ftp_chdir( $connection, $base_directory );

        if ( false === @ftp_mkdir( $connection, $current ) ) {
            return false;
        }
    }

    @ftp_chdir( $connection, $base_directory );

    return true;
}

/**
 * Deploy a directory tree using SFTP.
 */
function swtch_deploy_sftp(
    $host,
    $port,
    $username,
    $password,
    $remote_path,
    $local_root
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

    $remote_root = '/' . trim( $remote_path, '/' );

    if ( '/' === $remote_root || empty( trim( $remote_path ) ) ) {
        $remote_root = '/';
    }

    $stats = [
        'files'       => 0,
        'directories' => 0,
    ];

    $result = swtch_sftp_upload_directory(
        $sftp,
        $local_root,
        $remote_root,
        $stats
    );

    if ( true !== $result ) {
        return $result;
    }

    return [
        'success' => true,
        'message' => sprintf(
            'Deployment complete: %d files uploaded and %d directories created or verified.',
            $stats['files'],
            $stats['directories']
        ),
    ];
}

/**
 * Recursively upload a local directory over SFTP.
 */
function swtch_sftp_upload_directory(
    $sftp,
    $local_directory,
    $remote_directory,
    &$stats
) {

    $items = scandir( $local_directory );

    if ( false === $items ) {

        return [
            'success' => false,
            'message' => 'Could not read local export directory: ' . $local_directory,
        ];
    }

    foreach ( $items as $item ) {

        if ( '.' === $item || '..' === $item ) {
            continue;
        }

        $local_path = $local_directory . '/' . $item;

        $remote_path = rtrim( $remote_directory, '/' ) . '/' . $item;

        if ( is_dir( $local_path ) ) {

            if ( ! swtch_sftp_ensure_directory( $sftp, $remote_path ) ) {

                return [
                    'success' => false,
                    'message' => 'Could not create remote SFTP directory: ' . $remote_path,
                ];
            }

            $stats['directories']++;

            $result = swtch_sftp_upload_directory(
                $sftp,
                $local_path,
                $remote_path,
                $stats
            );

            if ( true !== $result ) {
                return $result;
            }

            continue;
        }

        $remote_url = sprintf(
            'ssh2.sftp://%d%s',
            intval( $sftp ),
            $remote_path
        );

        $source = @fopen( $local_path, 'rb' );
        $target = @fopen( $remote_url, 'wb' );

        if ( ! $source || ! $target ) {

            if ( is_resource( $source ) ) {
                fclose( $source );
            }

            if ( is_resource( $target ) ) {
                fclose( $target );
            }

            return [
                'success' => false,
                'message' => 'Could not open SFTP file for upload: ' . $remote_path,
            ];
        }

        $copied = stream_copy_to_stream(
            $source,
            $target
        );

        fclose( $source );
        fclose( $target );

        if ( false === $copied ) {

            return [
                'success' => false,
                'message' => 'Could not upload file by SFTP: ' . $remote_path,
            ];
        }

        $stats['files']++;
    }

    return true;
}

/**
 * Ensure an SFTP directory exists.
 */
function swtch_sftp_ensure_directory( $sftp, $remote_path ) {

    $remote_url = sprintf(
        'ssh2.sftp://%d%s',
        intval( $sftp ),
        $remote_path
    );

    if ( @is_dir( $remote_url ) ) {
        return true;
    }

    return @ssh2_sftp_mkdir(
        $sftp,
        $remote_path,
        0755,
        true
    );
}
