<article>
    <div class="accbutton">Contact Settings</div>

    <section class="hidden">

        <form method="post">

            <?php
            wp_nonce_field(
                'swtch_save_contact_settings',
                'swtch_contact_settings_nonce'
            );
            ?>

            <table class="form-table">

                <tr>
                    <th scope="row">
                        <label for="swtch_contact_enabled">
                            Enable Contact Handler
                        </label>
                    </th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                id="swtch_contact_enabled"
                                name="swtch_contact_enabled"
                                value="1"
                                <?php checked( $contact_enabled, true ); ?>
                            >
                            Generate the SWTCH contact form handler during site build.
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="swtch_smtp_host">
                            SMTP Host
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="swtch_smtp_host"
                            name="swtch_smtp_host"
                            value="<?php echo esc_attr( $smtp_host ); ?>"
                            class="regular-text"
                            placeholder="smtp.gmail.com"
                        >

                        <p class="description">
                            SMTP server hostname provided by your email provider.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="swtch_smtp_port">
                            SMTP Port
                        </label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="swtch_smtp_port"
                            name="swtch_smtp_port"
                            value="<?php echo esc_attr( $smtp_port ); ?>"
                            class="small-text"
                            min="1"
                            max="65535"
                            placeholder="587"
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="swtch_smtp_encryption">
                            Encryption
                        </label>
                    </th>
                    <td>
                        <select
                            id="swtch_smtp_encryption"
                            name="swtch_smtp_encryption"
                        >
                            <option value="tls" <?php selected( $smtp_encryption, 'tls' ); ?>>
                                STARTTLS
                            </option>

                            <option value="ssl" <?php selected( $smtp_encryption, 'ssl' ); ?>>
                                SSL/TLS
                            </option>

                            <option value="none" <?php selected( $smtp_encryption, 'none' ); ?>>
                                None
                            </option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="swtch_smtp_username">
                            SMTP Username
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="swtch_smtp_username"
                            name="swtch_smtp_username"
                            value="<?php echo esc_attr( $smtp_username ); ?>"
                            class="regular-text"
                            autocomplete="username"
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="swtch_smtp_password">
                            SMTP Password
                        </label>
                    </th>
                    <td>
                        <input
                            type="password"
                            id="swtch_smtp_password"
                            name="swtch_smtp_password"
                            value=""
                            class="regular-text"
                            autocomplete="new-password"
                        >

                        <p class="description">
                            Leave blank to keep the currently saved password.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="swtch_contact_to">
                            Send Contact Messages To
                        </label>
                    </th>
                    <td>
                        <input
                            type="email"
                            id="swtch_contact_to"
                            name="swtch_contact_to"
                            value="<?php echo esc_attr( $contact_to ); ?>"
                            class="regular-text"
                            placeholder="you@example.com"
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="swtch_contact_from_name">
                            From Name
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="swtch_contact_from_name"
                            name="swtch_contact_from_name"
                            value="<?php echo esc_attr( $contact_from_name ); ?>"
                            class="regular-text"
                            placeholder="Website Contact Form"
                        >
                    </td>
                </tr>

            </table>

            <?php
            submit_button(
                'Save Contact Settings',
                'secondary',
                'swtch_save_contact_settings'
            );
            ?>

        </form>

    </section>
</article>