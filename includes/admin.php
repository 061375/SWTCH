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
    $plugin_path = plugin_dir_path( __FILE__ );
    $show_deploy = "hidden";

    if(isset( $_POST['swtch_test_connection'] )) $show_deploy = "";
    ?>
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
        <!-- admin sections -->
        <?php include($plugin_path .'/../admin_sections/settings.php') ?>
        <?php include($plugin_path .'/../admin_sections/contact_settings.php') ?>
        <?php include($plugin_path .'/../admin_sections/build_site.php') ?>
        <?php include($plugin_path .'/../admin_sections/deploy_site.php') ?>
        <?php include($plugin_path .'/../admin_sections/instructions.php') ?>
        <!-- end admin sections -->
        <style>
                .swtch-log-success { color: #008a20; }
                .swtch-log-error { color: #b32d2e; }
                .swtch-log-info { color: #50575e; }
                .update-nag {display: none;}
                .accbutton {
                    font-size: 2em;
                    background: #d9d9d9;
                    padding: 14px 5px 14px;
                    border: solid 1px;
                    cursor: pointer;
                }
                section {
                    margin-left: 15px;
                }
                .warning {
                    color: red;
                }
                .todo {
                    background-color: black;
                    color: yellow;
                    padding: 3px 5px;
                }
                .todo > span 
                {
                    font-size: 1.3em;
                    font-weight: bold;
                }
                .i_section {
                    padding-left: 20px;
                }
        </style>
    </div>

    <?php
}
