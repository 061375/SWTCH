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
        </section>
    </article>