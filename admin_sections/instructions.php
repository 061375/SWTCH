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

            <div class="i_section">
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
            </div>


            <hr>


            <h2>2. Test the Deployment Connection</h2>

            <div class="i_section">
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
            </div>


            <hr>


            <h2>3. Build the Static Website</h2>

            <div class="i_section">
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

            <h3>301 Redirects</h3>
            <p>
                When SWTCH builds the static website, it also checks published pages and posts for permalink changes.
            </p>

            <p>
                If a permalink has changed since a previous build, SWTCH automatically generates a 301 redirect from the old URL to the current URL. This helps preserve existing links and search engine rankings when pages are renamed or moved.
            </p>

            <p>
                After the build completes, review the **301 Redirects** section below. If redirects were detected, click the redirect block or the **Copy Redirects** button to copy the generated rules to your clipboard.
            </p>

            <p>
                Paste the generated redirect block into the `.htaccess` file on the live website.
            </p>

            <p>
                SWTCH does not modify `.htaccess` automatically.
            </p>
            </div>


            <hr>


            <h2>4. Static File Structure</h2>

            <div class="i_section">
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

            </div>

            <hr>


            <h2>5. Asset Conversion</h2>

            <div class="i_section">
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
            </div>


            <hr>


            <h2>6. Sitemap</h2>

            <div class="i_section">
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
            </div>


            <hr>


            <h2>7. Deployment Manifest</h2>

            <div class="i_section">
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
            </div>


            <hr>


            <h2>8. Deploy the Website</h2>

            <div class="i_section">
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
            </div>


            <hr>


            <h2>9. Activity Log</h2>

            <div class="i_section">
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
            </div>


            <hr>


            <h2>10. Recommended Workflow</h2>

            <div class="i_section">
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
            </div>


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