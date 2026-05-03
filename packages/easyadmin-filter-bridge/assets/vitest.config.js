import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['controllers/__tests__/**/*.test.js'],
        // The Stimulus controller uses private class fields (`#…`).
        // Vitest's default node target supports them; explicit here for
        // CI environments that may pin older targets.
        globals: false,
    },
});
