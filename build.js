import * as esbuild from 'esbuild';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Uppy-CSS (aus node_modules kopiert): Verweise auf Source Maps entfernen.
// Seit @uppy/* 6.3 verweisen die .min.css auf eine externe style.min.css.map, die wir nicht ausliefern
// (sonst 404-Anfragen im Browser, auch aus den Bundles).
const stripSourceMap = css => css.replace(/\/\*#\s*sourceMappingURL=[^*]*\*\/\s*/g, '');
for (const file of [
    'assets/css/uppy-core.min.css',
    'assets/css/uppy-dashboard.min.css',
    'assets/css/uppy-webcam.min.css',
    'assets/css/uppy-image-editor.min.css',
]) {
    fs.writeFileSync(file, stripSourceMap(fs.readFileSync(file, 'utf8')));
}

// Backend Bundle (JavaScript)
await esbuild.build({
    entryPoints: ['assets/src/uppy-backend.js'],
    bundle: true,
    outfile: 'assets/dist/uppy-backend-bundle.js',
    format: 'iife',
    globalName: 'UppyBackend',
    platform: 'browser',
    target: ['es2020'],
    minify: true,
    sourcemap: true,
});

// Custom Widget Bundle (JavaScript)
await esbuild.build({
    entryPoints: ['assets/src/uppy-custom-widget.js'],
    bundle: true,
    outfile: 'assets/dist/uppy-custom-widget-bundle.js',
    format: 'iife',
    globalName: 'UppyCustomWidget',
    platform: 'browser',
    target: ['es2020'],
    minify: true,
    sourcemap: true,
});

// Backend CSS Bundle
const backendCssFiles = [
    'assets/css/uppy-core.min.css',
    'assets/css/uppy-dashboard.min.css',
    'assets/css/uppy-webcam.min.css',
    'assets/css/uppy-image-editor.min.css',
    'assets/css/uppy-custom-widget.css',
    'assets/css/uppy-dark-overrides.css',
    'assets/css/uppy-custom.css',
    'assets/src/uppy-dashboard-styles.css'
];

const backendCssBundle = backendCssFiles
    .map(file => fs.readFileSync(file, 'utf8'))
    .join('\n\n');

fs.writeFileSync('assets/dist/uppy-backend-bundle.css', backendCssBundle);

// Frontend CSS Bundle (nur essentielles für Custom Widget)
const frontendCssFiles = [
    'assets/css/uppy-core.min.css',
    'assets/css/uppy-dashboard.min.css',
    'assets/css/uppy-webcam.min.css',
    'assets/css/uppy-image-editor.min.css',
    'assets/css/uppy-custom-widget.css'
];

const frontendCssBundle = frontendCssFiles
    .map(file => fs.readFileSync(file, 'utf8'))
    .join('\n\n');

fs.writeFileSync('assets/dist/uppy-frontend-bundle.css', frontendCssBundle);

console.log('✓ Build complete');
console.log('✓ CSS bundles created');
console.log('  - uppy-backend-bundle.css');
console.log('  - uppy-frontend-bundle.css');
