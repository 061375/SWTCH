# SWTCH
## Static WordPress To Common HTML. Generate and deploy a static HTML version of a WordPress website.
## Jeremy Heminger <contact@jeremyheminger.com>

                                                ᓚᘏᗢ

# Dependancies


# Versions

## 📅 September 22, 2026
## ⬆️📅 November 11, 2026
## 0.2.19

* ## 0.2.18/2
*   🐞 SON-LD schema URLs were not being updated from http to https
* ## 0.2.18/1
*   🐞 Yoast JSON-LD schema URLs were not being updated from local
* ## 0.2.17/3
*   🐞 the categories and tags were not rendering
* ## 0.2.17/2
*   🐞 Pagination was not working for some scenarios 
* ## 0.2.17/1
*   🐱 bundle js and css files
* ## 0.2.12/1
*   🐱 Include categories, tags and pagination
* ## 0.2.11/1
*   🐱 Moved notifications to the top of the settings area 
* ## 0.2.10/1
*   🐞 Inline styles were not accounted for
* ## 0.2.9/1
*   🐞 Links to a local asset were not being converted 
* ## 0.1.7/1
*   🐱 Support for basic SMTP email and contact form
* ## 0.1.6/2
*   🐱 move admin sections to seperate folder for clarity
* ## 0.1.6/1
*   🐱 301 Redirect
* ## 0.1.5/1
*   🐱 Add warning about overwritting local login 
* ## 0.1.4/1
*   🐞 Stale files and assets were not being deleted on remote
* ## 0.1.3/3
*   🐱 Better UX
* ## 0.1.3/2
*   🐱 Instructions
* ## 0.1.3/1
*   🐱 Link to LIVE
* ## 0.1.2/1
*   🐱 Add accordion sections to admin
* ## 0.1.1/2
*   🐞 Homepage link was empty instead of /
* ## 0.1.1/1
*   🐱 Ajax support in Admin to view progress and prevent PHP timeout
* ## 0.1.0/1
*   🐱 First semi-working version
* ## 0.1.0

## JavaScript export optimization

Enable **JavaScript Optimization** in Settings to minify local classic JavaScript with Terser and combine
adjacent compatible script tags into content-hashed files under
`/<asset-base>/swtch-js/`. Execution order and inline configuration boundaries
are preserved. Inline scripts, modules, remote URLs, async/nomodule/integrity
scripts, and scripts using document.currentScript, document.write, or dynamic
imports are left alone. Tags with IDs remain separate to preserve their identity.
Blocking and deferred scripts are never mixed. Consequently a page may require
multiple bundles rather than one file. Original assets remain available for
runtime references. Global names are preserved; minification removes whitespace
and ordinary comments while retaining license comments.

Optimization is disabled by default. With it off, builds export original scripts and require neither Node.js nor Terser. Python is never a plugin runtime dependency. When optimization is enabled, the PHP worker needs Node.js on PATH and proc_open enabled. To install or restore
the pinned minifier: `cd lib/javascript && npm ci --omit=dev --ignore-scripts`.
Minifier errors retain original scripts and show a build warning. Node runs with --jitless for restricted PHP workers. Incomplete builds cannot upload, delete remote files, or save a deployment manifest; cleanup requires all uploads to succeed.

Builds stop on the first failed page or request and display an error notice. Deployment stays disabled until a complete build succeeds. Failed jobs cannot continue generating pages or be deployed. Recoverable optimization warnings retain original scripts and do not count as page failures.
