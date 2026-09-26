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
    <?php
    $show_deploy = "hidden";
    if(isset( $_POST['swtch_test_connection'] )) $show_deploy = "";
    ?>
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
        </section>
    </article>
    <article>
        <div class="accbutton">Deploy Site</div>
        <section class="<?=$show_deploy?>">
            <h3>Test Deployment Connection</h3>
            <p>Save the deployment settings above first, then enter the password to test the connection. The password is not saved.</p>
            <p class="warning">
                <i>
                    <b>
                        WARNING! 
                    </b>
                    Your browser may ask you to store this password. It you decide to do so be sure to modify the user name accordingly other wise you may overwrite the stored login to Wordpress for the current on this website
                </i>
            </p>
            <form method="post">
                <?php wp_nonce_field( 'swtch_test_connection', 'swtch_test_connection_nonce' ); ?>
                <table class="form-table"><tr><th scope="row">
                            <label for="swtch_deployment_password">
                                Password
                            </label>
                        </th>
                        <td>
                            <input type="password" id="swtch_deployment_password" name="swtch_deployment_password" class="regular-text" autocomplete="current-password">
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Test Connection', 'secondary', 'swtch_test_connection' ); ?>
            </form>

            <hr>

            <h2>Deploy Static Site</h2>
            <p>
                After a successful build, SWTCH scans the export directory and creates a deployment manifest.
                Remote ignore rules are applied before files are queued for upload.
            </p>
            <p class="warning">
                <i>
                    <b>
                        WARNING! 
                    </b>
                    Your browser may ask you to store this password. It you decide to do so be sure to modify the user name accordingly other wise you may overwrite the stored login to Wordpress for the current on this website
                </i>
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
                    <tr>
                        <th scope="row">
                            <button type="button" id="swtch-deploy" class="button button-secondary" disabled>
                                Deploy Static Site
                            </button>
                        </th>
                        <td>
                            <a href='<?= $live_site_url ?>' target="_blank">
                                View Live Website
                            </a>
                        </td>
                    </tr>
                </table>
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
            </style>
        </section>
    </article>
    <article>
        <div class="accbutton">Instructions</div>

        <section class="hidden">

            <h2>SWTCH User Guide</h2>

            <p>
                SWTCH converts a local WordPress website into a collection of
                static HTML, CSS, JavaScript, image, font, and other asset files
                that can be deployed to a standard web server.
            </p>

            <p>
                The normal workflow is:
            </p>

            <ol>
                <li>Configure SWTCH.</li>
                <li>Test the deployment connection.</li>
                <li>Build the static website.</li>
                <li>Review the build results.</li>
                <li>Deploy the generated website.</li>
            </ol>


            <hr>


            <h2>1. Configure SWTCH</h2>

            <p>
                Open the <strong>Settings</strong> section and configure the
                destination website and deployment connection.
            </p>

            <h3>Live Site URL</h3>

            <p>
                Enter the public URL where the static website will be hosted.
            </p>

            <p>
                Example:
            </p>

            <pre><code>https://example.com</code></pre>

            <p>
                SWTCH uses this address when converting WordPress URLs that must
                remain absolute, such as canonical URLs and social-media metadata.
            </p>


            <h3>Deployment Protocol</h3>

            <p>
                Select the protocol supported by your web host:
            </p>

            <ul>
                <li>
                    <strong>FTP</strong> &mdash; normally uses port
                    <code>21</code>.
                </li>
                <li>
                    <strong>SFTP</strong> &mdash; normally uses port
                    <code>22</code>.
                </li>
            </ul>


            <h3>Host</h3>

            <p>
                Enter the FTP or SFTP server hostname provided by your hosting
                company.
            </p>

            <p>
                Example:
            </p>

            <pre><code>ftp.example.com</code></pre>


            <h3>Port</h3>

            <p>
                Enter the server port used by the selected deployment protocol.
            </p>

            <ul>
                <li>FTP commonly uses <code>21</code>.</li>
                <li>SFTP commonly uses <code>22</code>.</li>
            </ul>


            <h3>Username</h3>

            <p>
                Enter the username used to connect to the remote server.
            </p>


            <h3>Remote Path</h3>

            <p>
                Enter the directory on the remote server where the website should
                be uploaded.
            </p>

            <p>
                Examples:
            </p>

            <pre><code>/
    /public_html/
    /www/</code></pre>

            <p>
                The correct value depends on your hosting provider. The path should
                point to the directory that serves the public website.
            </p>


            <h3>Remote Ignore Paths</h3>

            <p>
                Remote ignore rules prevent selected files or directories from
                being uploaded during deployment.
            </p>

            <p>
                Enter one rule per line.
            </p>

            <pre><code>robots.txt
    downloads/private/
    *.log
    # This is a comment</code></pre>

            <p>
                Rules work as follows:
            </p>

            <ul>
                <li>Blank lines are ignored.</li>
                <li>Lines beginning with <code>#</code> are comments.</li>
                <li>
                    A rule ending with <code>/</code> protects that directory
                    recursively.
                </li>
                <li>
                    Wildcards such as <code>*.log</code> may be used.
                </li>
            </ul>

            <p>
                SWTCH always protects the following remote paths:
            </p>

            <pre><code>.htaccess
    .well-known/</code></pre>

            <p>
                These built-in protections cannot be removed by the user.
            </p>


            <h3>Save Settings</h3>

            <p>
                Click <strong>Save Settings</strong> after changing the
                configuration.
            </p>


            <hr>


            <h2>2. Test the Deployment Connection</h2>

            <p>
                Before deploying the website, open the
                <strong>Deploy Site</strong> section and test the connection.
            </p>

            <p>
                Enter the FTP or SFTP password and click
                <strong>Test Connection</strong>.
            </p>

            <p>
                SWTCH will verify:
            </p>

            <ul>
                <li>That the server can be reached.</li>
                <li>That the username and password are accepted.</li>
                <li>That the configured remote directory can be opened.</li>
            </ul>

            <p>
                The deployment password is not stored in the SWTCH settings.
                It must be entered when the connection is tested or when a
                deployment is started.
            </p>


            <hr>


            <h2>3. Build the Static Website</h2>

            <p>
                Open the <strong>Build Site</strong> section and click
                <strong>Build Static Site</strong>.
            </p>

            <p>
                SWTCH creates a list of published WordPress pages and posts,
                including the site's homepage, and then processes each URL
                individually.
            </p>

            <p>
                During the build SWTCH:
            </p>

            <ul>
                <li>Requests the rendered WordPress page.</li>
                <li>Converts local WordPress URLs for static hosting.</li>
                <li>
                    Replaces <code>/wp-content/</code> URLs with the configured
                    asset directory.
                </li>
                <li>
                    Replaces <code>/wp-includes/</code> URLs with
                    <code>/core/</code>.
                </li>
                <li>
                    Finds referenced CSS, JavaScript, images, fonts, video,
                    audio, and related assets.
                </li>
                <li>Copies required assets into the static export.</li>
                <li>Creates an <code>index.html</code> file for each page.</li>
            </ul>

            <p>
                The progress bar shows how many WordPress pages have been
                processed.
            </p>


            <hr>


            <h2>4. Static File Structure</h2>

            <p>
                The exported website is created inside:
            </p>

            <pre><code>wp-content/swtch-export/</code></pre>

            <p>
                The homepage becomes:
            </p>

            <pre><code>swtch-export/index.html</code></pre>

            <p>
                A WordPress page such as:
            </p>

            <pre><code>/about/</code></pre>

            <p>
                becomes:
            </p>

            <pre><code>swtch-export/about/index.html</code></pre>

            <p>
                This allows normal directory-style URLs such as:
            </p>

            <pre><code>https://example.com/about/</code></pre>


            <hr>


            <h2>5. Asset Conversion</h2>

            <p>
                SWTCH removes WordPress-specific paths from the exported HTML.
            </p>

            <p>
                For example:
            </p>

            <pre><code>/wp-content/themes/example/style.css</code></pre>

            <p>
                becomes something similar to:
            </p>

            <pre><code>/assets/themes/example/style.css</code></pre>

            <p>
                WordPress core files normally referenced through:
            </p>

            <pre><code>/wp-includes/</code></pre>

            <p>
                are exported under:
            </p>

            <pre><code>/core/</code></pre>

            <p>
                SWTCH also examines CSS files for additional resources referenced
                through <code>url(...)</code> and <code>@import</code>, allowing
                fonts, background images, and related dependencies to be copied
                automatically.
            </p>


            <hr>


            <h2>6. Sitemap</h2>

            <p>
                After the pages have been built, SWTCH generates:
            </p>

            <pre><code>sitemap.xml</code></pre>

            <p>
                The sitemap is based on the WordPress sitemap data but only
                includes URLs that SWTCH actually exported.
            </p>

            <p>
                WordPress-only URLs that were not part of the static build, such
                as unwanted author or category archive pages, are therefore not
                automatically added to the static sitemap.
            </p>

            <p>
                Sitemap URLs are converted to the configured
                <strong>Live Site URL</strong>.
            </p>


            <hr>


            <h2>7. Deployment Manifest</h2>

            <p>
                When the build finishes, SWTCH scans the completed export directory
                and creates a deployment manifest.
            </p>

            <p>
                The manifest is the list of files that SWTCH intends to upload.
                Remote ignore rules are applied before files are placed into this
                list.
            </p>

            <p>
                Ignore rules are also checked again immediately before each file is
                uploaded.
            </p>


            <hr>


            <h2>8. Deploy the Website</h2>

            <p>
                After a successful build:
            </p>

            <ol>
                <li>Open <strong>Deploy Site</strong>.</li>
                <li>Enter the deployment password.</li>
                <li>Click <strong>Deploy Static Site</strong>.</li>
            </ol>

            <p>
                SWTCH uploads each file in the deployment manifest to the
                configured remote directory.
            </p>

            <p>
                Missing directories on the remote server are created
                automatically when possible.
            </p>

            <p>
                Deployment progress is displayed as each file is uploaded.
            </p>


            <hr>


            <h2>9. Activity Log</h2>

            <p>
                The <strong>Activity</strong> window displays the status of the
                current operation.
            </p>

            <p>
                During a build it reports generated or failed pages. During
                deployment it reports uploaded, skipped, or failed files.
            </p>

            <p>
                If deployment encounters an error, SWTCH stops at the failing file
                and displays the error message.
            </p>


            <hr>


            <h2>10. Recommended Workflow</h2>

            <ol>
                <li>Make changes to the local WordPress website.</li>
                <li>Save SWTCH settings if they have changed.</li>
                <li>Build the static website.</li>
                <li>Check the Activity log for errors.</li>
                <li>Test the deployment connection if necessary.</li>
                <li>Deploy the static website.</li>
                <li>
                    Use <strong>View Live Website</strong> to verify the published
                    result.
                </li>
            </ol>


            <hr>


            <h2>Important</h2>

            <p>
                SWTCH is intended to publish the generated static website.
                WordPress itself remains the local editing environment.
            </p>

            <p>
                Changes made in WordPress do not appear on the live static website
                until the site is rebuilt and deployed again.
            </p>

        </section>
    </article>
    </div>

    <?php
}
