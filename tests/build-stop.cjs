const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const code = fs.readFileSync(require('node:path').join(__dirname, '../assets/admin.js'), 'utf8');
function environment(responses) {
    const elements = new Map();
    const element = id => {
        if (!elements.has(id)) elements.set(id, {
            disabled: id === 'swtch-deploy', hidden: true, textContent: '', value: '',
            addEventListener(event, callback) { this[event] = callback; },
            appendChild() {},
        });
        return elements.get(id);
    };
    let ready;
    const calls = [];
    const context = {
        URLSearchParams, console, setTimeout,
        SWTCH_AJAX: {ajaxUrl: '/ajax', nonce: 'test'},
        document: {
            getElementById: element,
            createElement: () => ({}),
            addEventListener(event, callback) { ready = callback; }
        },
        fetch: async (_url, options) => {
            const action = new URLSearchParams(options.body).get('action');
            calls.push(action);
            const response = responses.shift();
            if (response instanceof Error) throw response;
            assert.ok(response, `Unexpected request: ${action}`);
            return {ok: response.success !== false, json: async () => response};
        }
    };
    vm.runInNewContext(code, context);
    ready();
    return {element, calls, responses};
}
const ok = data => ({success: true, data});
const start = () => ok({job_id: 'test', total: 3});
const page = success => ok({url: '/page', result: {success, message: 'Test page error'}});
const finalized = () => ok({pages_failed: 0, sitemap_created: true, deployment_total: 1, manifest: [], stale_total: 0});
async function stopped(env) {
    await env.element('swtch-build').click();
    assert.equal(env.element('swtch-deploy').disabled, true);
    assert.equal(env.element('swtch-build-error').hidden, false);
    assert.match(env.element('swtch-build-error').textContent, /Build stopped:.*Deployment is disabled/);
    assert.equal(env.element('swtch-build').disabled, false);
}
(async () => {
    for (const failure of [page(false), {success: false, data: {message: 'HTTP 500 for /page'}}, new Error('Network failure')]) {
        const env = environment([start(), page(true), failure]);
        await stopped(env);
        assert.deepEqual(env.calls, ['swtch_start_build', 'swtch_build_page', 'swtch_build_page']);
        await env.element('swtch-deploy').click();
        assert.equal(env.calls.length, 3);
        console.log('PASS: First page failure stops requests and blocks deployment');
        env.responses.push(start(), page(true), page(true), page(true), finalized());
        await env.element('swtch-build').click();
        assert.equal(env.element('swtch-deploy').disabled, false);
        assert.equal(env.element('swtch-build-error').hidden, true);
        console.log('PASS: A fresh successful build clears the error and enables deployment');
    }
    const failedStart = environment([new Error('Cannot start build')]);
    await stopped(failedStart);
    assert.equal(failedStart.calls.length, 1);
    const failedFinalize = environment([start(), page(true), page(true), page(true), {success: false, data: {message: 'Sitemap failed'}}]);
    await stopped(failedFinalize);
    console.log('PASS: Start and finalization errors keep deployment disabled');
})().catch(error => { console.error(error); process.exitCode = 1; });
