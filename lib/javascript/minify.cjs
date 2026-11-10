// Parse each script independently and keep global names intact.
const { minify } = require('terser');
let input = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', chunk => { input += chunk; });
process.stdin.on('end', async () => {
    try {
        const scripts = JSON.parse(input);
        const output = [];
        for (const script of scripts) {
            const result = await minify(script, {
                compress: false,
                mangle: false,
                format: { comments: /@license|@preserve|^!/ }
            });
            output.push(result.code);
        }
        process.stdout.write(output.join('\n;\n') + '\n');
    } catch (error) {
        process.stderr.write(error.message + '\n');
        process.exitCode = 1;
    }
});
