import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['controllers/__tests__/**/*.test.js'],
        globals: false,
    },
});
