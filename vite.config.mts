/// <reference types="vitest/config" />
import { createHash } from 'node:crypto';
import { basename, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const repositoryRoot = fileURLToPath(new URL('.', import.meta.url));
const builderRoot = resolve(repositoryRoot, 'assets/builder');
const shopRoot = resolve(repositoryRoot, 'assets/shop');

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

/**
 * The shop's carousel script (--mode shop), from assets/shop: a classic script with no dependencies, which every shop
 * page loads, next to the hand-written stylesheet in src/Resources/public/shop. It's left unminified, so a project that
 * bundles it into its own build reads it as it is.
 */
const shopBuild = {
    outDir: resolve(repositoryRoot, 'src/Resources/public/shop'),
    // The directory holds the hand-written cms.css too
    emptyOutDir: false,
    target: 'es2020',
    sourcemap: false,
    minify: false,
    modulePreload: false,
    reportCompressedSize: false,
    rollupOptions: {
        input: resolve(shopRoot, 'src/cms.ts'),
        output: {
            format: 'iife' as const,
            entryFileNames: 'cms.js',
            inlineDynamicImports: true,
        },
    },
};

/**
 * Two builds into one directory: the builder app (the default mode), then the bridge script that preview mode injects
 * into the shop page (--mode bridge). The bridge is a separate script with no React, which runs alone. It carries
 * Tiptap, with the shared rich text schema, for typing rich text in place. A third build, the shop script (--mode
 * shop), goes to its own directory.
 */
export default defineConfig(({ mode }) => ({
    root: builderRoot,
    cacheDir: resolve(repositoryRoot, 'node_modules/.vite'),
    publicDir: false,
    plugins: [react()],
    css: {
        modules: {
            generateScopedName: scopedClassName,
        },
    },
    build:
        mode === 'shop'
            ? shopBuild
            : {
                  // Committed to the repository and installed by assets:install. CI rebuilds it and fails on any diff,
                  // so the output has fixed names, one module and one stylesheet, and no source maps.
                  outDir: resolve(repositoryRoot, 'src/Resources/public/builder'),
                  emptyOutDir: mode !== 'bridge',
                  target: 'es2022',
                  sourcemap: false,
                  cssCodeSplit: false,
                  modulePreload: false,
                  reportCompressedSize: false,
                  // One module by design, and Tiptap with ProseMirror makes up about half of it
                  chunkSizeWarningLimit: 1024,
                  rollupOptions: {
                      input: resolve(builderRoot, mode === 'bridge' ? 'src/bridge/main.ts' : 'src/main.tsx'),
                      output: {
                          entryFileNames: mode === 'bridge' ? 'bridge.js' : 'builder.js',
                          inlineDynamicImports: true,
                          assetFileNames: (asset) => (asset.names.some((name) => name.endsWith('.css')) ? 'builder.css' : '[name][extname]'),
                      },
                  },
              },
    test: {
        environment: 'jsdom',
        // The builder's tests, and the shop script's next to its own source
        include: ['tests/**/*.test.{ts,tsx}', '../shop/tests/**/*.test.ts'],
        setupFiles: ['tests/setup.ts'],
    },
}));
