(function () {
    'use strict';

    const state = {
        jobId: null,
        pageTotal: 0,
        deploymentTotal: 0,
        deploymentManifest: []
    };

    function request(action, data = {}) {
        const body = new URLSearchParams({
            action,
            nonce: SWTCH_AJAX.nonce,
            ...data
        });

        return fetch(SWTCH_AJAX.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            credentials: 'same-origin',
            body: body.toString()
        }).then(async response => {
            const json = await response.json();

            if (!response.ok || !json.success) {
                const message = json?.data?.message || 'SWTCH request failed.';
                throw new Error(message);
            }

            return json.data;
        });
    }

    function setProgress(id, current, total) {
        const bar = document.getElementById(id);
        if (!bar) return;

        const percent = total > 0 ? Math.round((current / total) * 100) : 0;
        bar.value = percent;

        const label = document.getElementById(id + '-label');
        if (label) {
            label.textContent = `${current} / ${total} (${percent}%)`;
        }
    }

    function log(message, type = 'info') {
        const output = document.getElementById('swtch-log');
        if (!output) return;

        const row = document.createElement('div');
        row.className = `swtch-log-${type}`;
        row.textContent = message;
        output.appendChild(row);
        output.scrollTop = output.scrollHeight;
    }

    async function buildPages() {
        const started = await request('swtch_start_build');

        state.jobId = started.job_id;
        state.pageTotal = started.total;

        log(`Build manifest created with ${started.total} page(s).`);
        setProgress('swtch-build-progress', 0, started.total);

        for (let index = 0; index < started.total; index++) {
            const data = await request('swtch_build_page', {
                job_id: state.jobId,
                index: String(index)
            });

            const ok = !!data.result.success;
            log(
                `${ok ? 'Generated' : 'Failed'}: ${data.url}${ok ? '' : ' — ' + data.result.message}`,
                ok ? 'success' : 'error'
            );

            setProgress('swtch-build-progress', index + 1, started.total);
        }

        const finalized = await request('swtch_finalize_build', {
            job_id: state.jobId
        });

        state.deploymentTotal = finalized.deployment_total;
        state.deploymentManifest = finalized.manifest;

        log(`Deployment manifest created with ${finalized.deployment_total} file(s).`);
        log(
            finalized.sitemap_created
                ? 'sitemap.xml created.'
                : 'sitemap.xml could not be created.',
            finalized.sitemap_created ? 'success' : 'error'
        );

        const deployButton = document.getElementById('swtch-deploy');
        if (deployButton) {
            deployButton.disabled = finalized.deployment_total === 0;
        }

        setProgress('swtch-deploy-progress', 0, finalized.deployment_total);
    }

    async function deployFiles() {
        if (!state.jobId || state.deploymentTotal === 0) {
            return;
        }
        const password = document.getElementById('swtch-deployment-password')?.value || '';
        for (let index = 0; index < state.deploymentTotal; index++) {
            const item = state.deploymentManifest[index];

            try {
                const data = await request('swtch_deploy_file', {
                    job_id: state.jobId,
                    index: String(index),
                    password: password
                });

                log(`${data.skipped ? 'Skipped' : 'Uploaded'}: ${data.file}`, data.skipped ? 'info' : 'success');
            } catch (error) {
                log(`Deployment stopped at ${item.relative_path}: ${error.message}`, 'error');
                throw error;
            }

            setProgress('swtch-deploy-progress', index + 1, state.deploymentTotal);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const buildButton = document.getElementById('swtch-build');
        const deployButton = document.getElementById('swtch-deploy');

        if (buildButton) {
            buildButton.addEventListener('click', async function () {
                buildButton.disabled = true;
                if (deployButton) deployButton.disabled = true;

                const output = document.getElementById('swtch-log');
                if (output) output.innerHTML = '';

                try {
                    await buildPages();
                    log('Build complete.', 'success');
                } catch (error) {
                    log(error.message, 'error');
                } finally {
                    buildButton.disabled = false;
                }
            });
        }

        if (deployButton) {
            deployButton.addEventListener('click', async function () {
                deployButton.disabled = true;

                try {
                    await deployFiles();
                    log('Deployment complete.', 'success');
                } catch (error) {
                    // The detailed error was already logged at the failing file.
                } finally {
                    deployButton.disabled = false;
                }
            });
        }

        document.getElementById('wpwrap').addEventListener('click', function (event) {
            const button = event.target.closest('.accbutton');

            if (!button) {
                return;
            }

            const article = button.closest('article');

            if (!article) {
                return;
            }

            const section = article.querySelector('section');

            if (section) {
                section.classList.toggle('hidden');
            }
        });
    });
})();
