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