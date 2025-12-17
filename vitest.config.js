import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['tests/Unit/Vue/**/*.test.js'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'json', 'html'],
            include: ['media/com_nxpeasycart/src/app/composables/**/*.js'],
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'media/com_nxpeasycart/src'),
            '@app': resolve(__dirname, 'media/com_nxpeasycart/src/app'),
            '@composables': resolve(__dirname, 'media/com_nxpeasycart/src/app/composables'),
        },
    },
});
