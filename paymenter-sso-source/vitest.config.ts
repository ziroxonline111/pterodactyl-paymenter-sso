import { defineConfig } from 'vitest/config';
import { pterodactylTestAliases, pterodactylTestSetup } from '../sdk/vitest.mjs';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    root: fileURLToPath(new URL('.', import.meta.url)),
    resolve: {
        alias: pterodactylTestAliases(),
    },
    test: {
        name: 'paymenter-sso',
        environment: 'jsdom',
        setupFiles: [pterodactylTestSetup],
        include: ['frontend/**/*.spec.{ts,tsx}'],
        clearMocks: true,
        restoreMocks: true,
    },
});
