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

        <h2>301 Redirects</h2>
        <p>
            SWTCH remembers previous WordPress permalinks and generates Apache 301 redirects
            for URLs that have changed. Copy this block into the live site's
            <code>.htaccess</code> file.
        </p>

        <p>
            <button type="button" id="swtch-copy-301" class="button button-secondary" disabled>
                Copy Redirects
            </button>
            <span id="swtch-301-count" style="margin-left:8px;"></span>
        </p>

        <pre
            id="swtch-301"
            class="code"
            title="Click to select and copy redirects"
            style="
                cursor:pointer;
                user-select:text;
                max-width:1000px;
                min-height:100px;
                overflow:auto;
                background:#fff;
                border:1px solid #c3c4c7;
                padding:12px;
                white-space:pre-wrap;
            "
        ># Redirects will appear here after a build.</pre>
    </section>
</article>
