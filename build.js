import * as esbuild from 'esbuild';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Backend Bundle
await esbuild.build({
    entryPoints: ['src/uppy-backend.js'],
    bundle: true,
    outfile: 'assets/uppy-backend-bundle.js',
    format: 'iife',
    globalName: 'UppyBackend',
    platform: 'browser',
    target: ['es2020'],
    minify: false,
    sourcemap: true,
});

// Frontend Bundle
await esbuild.build({
    entryPoints: ['src/uppy-frontend.js'],
    bundle: true,
    outfile: 'assets/uppy-frontend-bundle.js',
    format: 'iife',
    globalName: 'UppyFrontend',
    platform: 'browser',
    target: ['es2020'],
    minify: false,
    sourcemap: true,
});

console.log('✓ Build complete');
