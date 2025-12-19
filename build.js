import * as esbuild from 'esbuild';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Backend Bundle
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

// Custom Widget Bundle
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

// Copy CSS file
fs.copyFileSync(
    'assets/src/uppy-dashboard-styles.css',
    'assets/uppy-dashboard-styles.css'
);

console.log('✓ Build complete');
console.log('✓ CSS copied');
