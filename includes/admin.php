<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

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
 * Load SWTCH admin JavaScript only on the SWTCH settings page.
 */
function swtch_admin_assets( $hook_suffix ) {

    if ( 'settings_page_swtch' !== $hook_suffix ) {
        return;
    }

    wp_enqueue_script(
        'swtch-admin',
        plugin_dir_url( SWTCH_PLUGIN_FILE ) . 'assets/admin.js',
        [],
        '0.2.0',
        true
    );

    wp_localize_script(
        'swtch-admin',
        'SWTCH_AJAX',
        [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'swtch_ajax' ),
        ]
    );
}
add_action( 'admin_enqueue_scripts', 'swtch_admin_assets' );

/**
 * Save settings before rendering the page.
 */
function swtch_handle_settings_save() {

    if ( isset( $_POST['swtch_save_settings'] ) ) {
       

        if (
            ! isset( $_POST['swtch_settings_nonce'] ) ||
            ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['swtch_settings_nonce'] ) ),
                'swtch_save_settings'
            )
        ) {
            wp_die( 'Security check failed.' );
        }

        $live_site_url = '';

        if ( isset( $_POST['swtch_live_site_url'] ) ) {
            $live_site_url = untrailingslashit(
                esc_url_raw(
                    wp_unslash( $_POST['swtch_live_site_url'] )
                )
            );
        }

        update_option( 'swtch_live_site_url', $live_site_url );

        $remote_ignore_paths = '';

        if ( isset( $_POST['swtch_remote_ignore_paths'] ) ) {
            $remote_ignore_paths = sanitize_textarea_field(
                wp_unslash( $_POST['swtch_remote_ignore_paths'] )
            );
        }

        update_option( 'swtch_remote_ignore_paths', $remote_ignore_paths );

        $deployment_protocol = isset( $_POST['swtch_deployment_protocol'] ) ? sanitize_key( wp_unslash( $_POST['swtch_deployment_protocol'] ) ) : 'ftp';
        if ( ! in_array( $deployment_protocol, [ 'ftp', 'sftp' ], true ) ) { $deployment_protocol = 'ftp'; }
        $deployment_host = isset( $_POST['swtch_deployment_host'] ) ? sanitize_text_field( wp_unslash( $_POST['swtch_deployment_host'] ) ) : '';
        $deployment_port = isset( $_POST['swtch_deployment_port'] ) ? absint( $_POST['swtch_deployment_port'] ) : ( 'sftp' === $deployment_protocol ? 22 : 21 );
        if ( $deployment_port < 1 || $deployment_port > 65535 ) { $deployment_port = 'sftp' === $deployment_protocol ? 22 : 21; }
        $deployment_username = isset( $_POST['swtch_deployment_username'] ) ? sanitize_text_field( wp_unslash( $_POST['swtch_deployment_username'] ) ) : '';
        $deployment_remote_path = isset( $_POST['swtch_deployment_remote_path'] ) ? sanitize_text_field( wp_unslash( $_POST['swtch_deployment_remote_path'] ) ) : '/';
        if ( empty( $deployment_remote_path ) ) { $deployment_remote_path = '/'; }
        update_option( 'swtch_deployment_protocol', $deployment_protocol );
        update_option( 'swtch_deployment_host', $deployment_host );
        update_option( 'swtch_deployment_port', $deployment_port );
        update_option( 'swtch_deployment_username', $deployment_username );
        update_option( 'swtch_deployment_remote_path', $deployment_remote_path );

        $remote_ignore_paths = '';

        if ( isset( $_POST['swtch_remote_ignore_paths'] ) ) {
            $remote_ignore_paths = sanitize_textarea_field(
                wp_unslash( $_POST['swtch_remote_ignore_paths'] )
            );
        }

        // echo '<div class="notice notice-success is-dismissible">';
        // echo '<p>SWTCH settings saved.</p>';
        // echo '</div>';
        
        return true;

    } // <--- END swtch_save_settings 

    /* Handle Deploy Static Site button. */
    if ( isset( $_POST['swtch_deploy'] ) ) {
        if ( ! isset( $_POST['swtch_deploy_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['swtch_deploy_nonce'] ) ), 'swtch_deploy_site' ) ) { wp_die( 'Security check failed.' ); }
        $password = isset( $_POST['swtch_deploy_password'] ) ? (string) wp_unslash( $_POST['swtch_deploy_password'] ) : '';
        $deployment_result = swtch_deploy_static_site( swtch_get_deployment_protocol(), swtch_get_deployment_host(), swtch_get_deployment_port(), swtch_get_deployment_username(), $password, swtch_get_deployment_remote_path() );
        $notice_class = $deployment_result['success'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $deployment_result['message'] ) . '</p></div>';
    }

    /* Handle Test Connection button. */
    if ( isset( $_POST['swtch_test_connection'] ) ) {
        if ( ! isset( $_POST['swtch_test_connection_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['swtch_test_connection_nonce'] ) ), 'swtch_test_connection' ) ) { wp_die( 'Security check failed.' ); }
        $password = isset( $_POST['swtch_deployment_password'] ) ? (string) wp_unslash( $_POST['swtch_deployment_password'] ) : '';
        $connection_result = swtch_test_deployment_connection( swtch_get_deployment_protocol(), swtch_get_deployment_host(), swtch_get_deployment_port(), swtch_get_deployment_username(), $password, swtch_get_deployment_remote_path() );
        $notice_class = $connection_result['success'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $connection_result['message'] ) . '</p></div>';
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
    } // <-- END swtch_generate


    return false;
}

/**
 * Render the SWTCH administration page.
 */
function swtch_render_admin_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings_saved = swtch_handle_settings_save();
    $live_site_url  = swtch_get_live_site_url();
    $deployment_protocol    = swtch_get_deployment_protocol();
    $deployment_host        = swtch_get_deployment_host();
    $deployment_port        = swtch_get_deployment_port();
    $deployment_username    = swtch_get_deployment_username();
    $deployment_remote_path = swtch_get_deployment_remote_path();
    ?>
    <style>
        .update-nag {display: none;}
        .accbutton {
            font-size: 2em;
            background: #d9d9d9;
            padding: 14px 5px 14px;
            border: solid 1px;
            cursor: pointer;
        }
    </style>
    <div class="wrap">

        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
            <img
                src="<?php echo esc_url( plugin_dir_url( SWTCH_PLUGIN_FILE ) . 'swtch-logo.png' ); ?>"
                alt="SWTCH"
                style="margin: 15px 0 0 0;width:150px;height:150px;object-fit:contain;border-top-left-radius:35px;border-top-right-radius:35px;box-shadow:-2px -3px 2px 0 #000;"
            >
        </div>

        <p>Static WordPress To Common HTML</p>

        <?php if ( $settings_saved ) : ?>
            <div class="notice notice-success is-dismissible">
                <p>SWTCH settings saved.</p>
            </div>
        <?php endif; ?>

        <hr>
    <article>
        <div class="accbutton">Settings</div>
        <section class="hidden">
            <form method="post">
                <?php wp_nonce_field( 'swtch_save_settings', 'swtch_settings_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="swtch_live_site_url">Live Site URL</label>
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
                    <tr><th scope="row"><label for="swtch_deployment_protocol">Deployment Protocol</label></th><td><select id="swtch_deployment_protocol" name="swtch_deployment_protocol"><option value="ftp" <?php selected( $deployment_protocol, 'ftp' ); ?>>FTP</option><option value="sftp" <?php selected( $deployment_protocol, 'sftp' ); ?>>SFTP (SSH)</option></select><p class="description">FTP normally uses port 21. SFTP normally uses port 22.</p></td></tr>
                    <tr><th scope="row"><label for="swtch_deployment_host">Host</label></th><td><input type="text" id="swtch_deployment_host" name="swtch_deployment_host" value="<?php echo esc_attr( $deployment_host ); ?>" class="regular-text" placeholder="ftp.example.com"></td></tr>
                    <tr><th scope="row"><label for="swtch_deployment_port">Port</label></th><td><input type="number" id="swtch_deployment_port" name="swtch_deployment_port" value="<?php echo esc_attr( $deployment_port ); ?>" class="small-text" min="1" max="65535"></td></tr>
                    <tr><th scope="row"><label for="swtch_deployment_username">Username</label></th><td><input type="text" id="swtch_deployment_username" name="swtch_deployment_username" value="<?php echo esc_attr( $deployment_username ); ?>" class="regular-text" autocomplete="username"></td></tr>
                    <tr><th scope="row"><label for="swtch_deployment_remote_path">Remote Path</label></th><td><input type="text" id="swtch_deployment_remote_path" name="swtch_deployment_remote_path" value="<?php echo esc_attr( $deployment_remote_path ); ?>" class="regular-text" placeholder="/public_html/"><p class="description">Directory where the static site will be uploaded.</p></td></tr>
                    <tr>
                        <th scope="row">
                            <label for="swtch_remote_ignore_paths">Remote Ignore Paths</label>
                        </th>
                        <td>
                            <textarea
                                id="swtch_remote_ignore_paths"
                                name="swtch_remote_ignore_paths"
                                rows="8"
                                class="large-text code"
                                placeholder="robots.txt&#10;custom/&#10;downloads/private/&#10;*.log"
                            ><?php echo esc_textarea( swtch_get_remote_ignore_rules_text() ); ?></textarea>

                            <p class="description">
                                One path or wildcard per line. Blank lines and lines beginning with # are ignored.
                                Directory rules ending in / apply recursively. SWTCH always protects
                                <code>.htaccess</code> and <code>.well-known/</code>.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Settings', 'secondary', 'swtch_save_settings' ); ?>
            </form>
        </section>
    </article>
    <article>
        <div class="accbutton">Build Site</div>
        <section class="hidden">
            <p>
                SWTCH first creates a page manifest, then generates one page per AJAX request.
            </p>

            <p>
                <button type="button" id="swtch-build" class="button button-primary">
                    Build Static Site
                </button>
            </p>

            <div style="max-width:760px;margin:14px 0 24px;">
                <strong>Build progress</strong><br>
                <progress id="swtch-build-progress" value="0" max="100" style="width:100%;height:22px;"></progress>
                <div id="swtch-build-progress-label">0 / 0 (0%)</div>
            </div>

            <h3>Test Deployment Connection</h3>
            <p>Save the deployment settings above first, then enter the password to test the connection. The password is not saved.</p>
            <form method="post">
                <?php wp_nonce_field( 'swtch_test_connection', 'swtch_test_connection_nonce' ); ?>
                <table class="form-table"><tr><th scope="row"><label for="swtch_deployment_password">Password</label></th><td><input type="password" id="swtch_deployment_password" name="swtch_deployment_password" class="regular-text" autocomplete="current-password"></td></tr></table>
                <?php submit_button( 'Test Connection', 'secondary', 'swtch_test_connection' ); ?>
            </form>

            <hr>

            <h2>Deploy Static Site</h2>
            <p>
                After a successful build, SWTCH scans the export directory and creates a deployment manifest.
                Remote ignore rules are applied before files are queued for upload.
            </p>

            <p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="swtch-deployment-password">
                                Password
                            </label>
                        </th>
                        <td>
                            <input
                                type="password"
                                id="swtch-deployment-password"
                                autocomplete="current-password"
                                class="regular-text"
                            >
                        </td>
                    </tr>
                </table>
                    
                <button type="button" id="swtch-deploy" class="button button-secondary" disabled>
                    Deploy Static Site
                </button>
                
            </p>

            <div style="max-width:760px;margin:14px 0 24px;">
                <strong>Deployment progress</strong><br>
                <progress id="swtch-deploy-progress" value="0" max="100" style="width:100%;height:22px;"></progress>
                <div id="swtch-deploy-progress-label">0 / 0 (0%)</div>
            </div>

            <h2>Activity</h2>
            <div
                id="swtch-log"
                class="code"
                style="max-width:1000px;height:300px;overflow:auto;background:#fff;border:1px solid #c3c4c7;padding:12px;white-space:pre-wrap;"
            ></div>

            <style>
                .swtch-log-success { color: #008a20; }
                .swtch-log-error { color: #b32d2e; }
                .swtch-log-info { color: #50575e; }
            </style>
        </section>
    </article>
    </div>

    <?php
}
