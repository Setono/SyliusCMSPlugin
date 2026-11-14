/// <reference types="vitest/config" />
import { createHash } from 'node:crypto';
import { basename, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const repositoryRoot = fileURLToPath(new URL('.', import.meta.url));
const builderRoot = resolve(repositoryRoot, 'assets/builder');

/**
 * Names CSS Modules classes after their file and local name, plus a hash of the file's path relative
 * to the repository. The committed build must not depend on where the repository is checked out.
 */
function scopedClassName(localName: string, filename: string): string {
    const file = filename.split('?')[0] ?? filename;
    const path = relative(repositoryRoot, file).split(sep).join('/');
    const hash = createHash('sha256').update(`${path}:${localName}`).digest('hex').slice(0, 6);

    return `${basename(file).replace(/\.module\.css$/, '')}_${localName}_${hash}`;
}

export default defineConfig({
    root: builderRoot,
    cacheDir: resolve(repositoryRoot, 'node_modules/.vite'),
    publicDir: false,
    plugins: [react()],
    css: {
        modules: {
            generateScopedName: scopedClassName,
        },
    },
    build: {
        // Committed to the repository and installed by assets:install. CI rebuilds it and fails on any diff,
        // so the output has fixed names, one module and one stylesheet, and no source maps.
        outDir: resolve(repositoryRoot, 'src/Resources/public/builder'),
        emptyOutDir: true,
        target: 'es2022',
        sourcemap: false,
        cssCodeSplit: false,
        modulePreload: false,
        reportCompressedSize: false,
        rollupOptions: {
            input: resolve(builderRoot, 'src/main.tsx'),
            output: {
                entryFileNames: 'builder.js',
                inlineDynamicImports: true,
                assetFileNames: (asset) => (asset.names.some((name) => name.endsWith('.css')) ? 'builder.css' : '[name][extname]'),
            },
        },
    },
    test: {
        environment: 'jsdom',
        include: ['tests/**/*.test.{ts,tsx}'],
        setupFiles: ['tests/setup.ts'],
    },
});
