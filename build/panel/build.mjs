// Builds what the bundle ships prebuilt (npm ci && npm run build):
//
// 1. assets/vendor/ - the third-party code the panel's own assets import by relative paths, so they resolve with any
//    tool or none (Vite, Encore, AssetMapper, the standalone Tailwind CLI): gridstack and vanilla-calendar-pro as one
//    ES module each, the stylesheets (and fonts) of Bootstrap Icons, gridstack, vanilla-calendar-pro and Tom Select,
//    their licences;
// 2. public/panel/ - the built-in assets (byfareska_crud.assets: builtin): panel.css and panel.js with its chunks.
import { execFileSync } from 'node:child_process';
import { cpSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const here = dirname(fileURLToPath(import.meta.url));
const modules = resolve(here, 'node_modules');
const vendor = resolve(here, '../../assets/vendor');
const out = resolve(here, '../../public/panel');
const tmp = resolve(here, 'var');

// 1. assets/vendor
rmSync(vendor, { recursive: true, force: true });
mkdirSync(vendor, { recursive: true });

for (const name of ['gridstack', 'vanilla-calendar-pro']) {
    await esbuild.build({
        entryPoints: [resolve(modules, name)],
        outfile: resolve(vendor, `${name}.js`),
        bundle: true,
        format: 'esm',
        minify: true,
        target: 'es2020',
        legalComments: 'inline',
        logLevel: 'warning',
    });
}

const copies = {
    'bootstrap-icons/font/bootstrap-icons.min.css': 'bootstrap-icons/bootstrap-icons.min.css',
    'bootstrap-icons/font/fonts': 'bootstrap-icons/fonts',
    'gridstack/dist/gridstack.min.css': 'gridstack/gridstack.min.css',
    'vanilla-calendar-pro/styles/core.css': 'vanilla-calendar-pro/core.css',
    'vanilla-calendar-pro/styles/months.css': 'vanilla-calendar-pro/months.css',
    'tom-select/dist/css/tom-select.default.css': 'tom-select/tom-select.default.css',
    'tom-select/dist/css/tom-select.bootstrap5.css': 'tom-select/tom-select.bootstrap5.css',
};
for (const [from, to] of Object.entries(copies)) {
    cpSync(resolve(modules, from), resolve(vendor, to), { recursive: true });
}

// What is in it, of which version, under which licence.
const notice = ['# Third-party code of the panel', '', 'Built by build/panel (npm run build) - do not edit.', ''];
for (const name of ['bootstrap-icons', 'gridstack', 'tom-select', 'vanilla-calendar-pro']) {
    const json = JSON.parse(readFileSync(resolve(modules, name, 'package.json'), 'utf8'));
    mkdirSync(resolve(vendor, name), { recursive: true });
    cpSync(resolve(modules, name, 'LICENSE'), resolve(vendor, name, 'LICENSE'));
    notice.push(`- ${name} ${json.version} (${json.license}): ${name}/LICENSE`);
}
writeFileSync(resolve(vendor, 'README.md'), notice.join('\n') + '\n');

// 2. public/panel
rmSync(out, { recursive: true, force: true });
mkdirSync(out, { recursive: true });
mkdirSync(tmp, { recursive: true });

// Tailwind first: the classes of the templates, daisyUI, the imports inlined.
execFileSync(
    resolve(modules, '.bin/tailwindcss'),
    ['--input', resolve(here, 'panel.css'), '--output', resolve(tmp, 'panel.css'), '--minify'],
    { stdio: 'inherit', cwd: here },
);

// Then esbuild minifies it; the fonts of the icons keep their addresses (fonts/...), copied next to it.
await esbuild.build({
    entryPoints: { panel: resolve(tmp, 'panel.css') },
    outdir: out,
    bundle: true,
    minify: true,
    external: ['fonts/*'],
    logLevel: 'info',
});
cpSync(resolve(vendor, 'bootstrap-icons/fonts'), resolve(out, 'fonts'), { recursive: true });

// The script: ES modules, gridstack and the calendar in chunks of their own (loaded on the pages that use them).
await esbuild.build({
    entryPoints: { panel: resolve(here, 'panel.js') },
    outdir: out,
    bundle: true,
    splitting: true,
    format: 'esm',
    minify: true,
    target: 'es2020',
    chunkNames: 'chunks/[name]-[hash]',
    nodePaths: [modules],
    logLevel: 'info',
});

rmSync(tmp, { recursive: true, force: true });
