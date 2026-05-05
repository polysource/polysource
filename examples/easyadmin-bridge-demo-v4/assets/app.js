// Demo entry point — boots Stimulus and lets symfony/stimulus-bundle
// auto-register every controller declared by `vendor/*/package.json`'s
// `symfony.controllers` manifest. The bridge ships the
// `polysource--filter` controller this way (see
// `vendor/polysource/easyadmin-filter-bridge/package.json`).

import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();

// Optional: expose for debugging from the browser console.
window.Stimulus = app;
