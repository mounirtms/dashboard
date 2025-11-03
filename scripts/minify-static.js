const fs = require('fs-extra');
const path = require('path');
const { minify: minifyJS } = require('terser');
const CleanCSS = require('clean-css');
const glob = require('glob');

const STATIC_DIR = '/home/technadminy7/public_html/pub/static/frontend';

const minifyFile = async (filePath) => {
    const ext = path.extname(filePath);
    let content;

    try {
        content = await fs.readFile(filePath, 'utf8');
    } catch (err) {
        console.warn(`⚠️ Skipping binary/unreadable file: ${filePath}`);
        return;
    }

    let minified;

    try {
        if (ext === '.css') {
            minified = new CleanCSS({ level: 2 }).minify(content).styles;
        } else if (ext === '.js') {
            // Skip already minified files
            if (filePath.includes('.min.')) {
                return;
            }
            const result = await minifyJS(content, {
                compress: {
                    drop_console: true,
                    drop_debugger: true,
                    pure_funcs: ['console.log', 'console.info', 'console.debug']
                },
                mangle: true,
                sourceMap: false
            });
            minified = result.code;
        } else {
            return;
        }

        if (minified) {
            await fs.writeFile(filePath, minified, 'utf8');
            console.log(`✅ Minified: ${filePath}`);
        }
    } catch (err) {
        console.error(`❌ Error minifying ${filePath}:`, err.message);
    }
};

const runMinify = async () => {
    const patterns = ['**/*.css', '**/*.js'];

    for (const pattern of patterns) {
        const files = glob.sync(pattern, {
            cwd: STATIC_DIR,
            absolute: true,
            nodir: true,
            ignore: ['**/node_modules/**', '**/*.min.css', '**/*.min.js']
        });

        for (const file of files) {
            await minifyFile(file);
        }
    }

    console.log('🎉 Static minification complete.');
};

runMinify();
