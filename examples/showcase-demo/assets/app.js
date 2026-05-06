import './stimulus_bootstrap.js';
import './styles/app.css';
import 'bootstrap/dist/css/bootstrap.min.css';
// Turbo intentionally NOT imported. EA's app.js binds modal/dropdown/
// filter handlers on `DOMContentLoaded`, which Turbo's body-swap
// navigation does NOT re-fire. The showcase has been losing the EA
// theme + filter button binding on every Turbo nav since the
// importmap was wired up. We're a demo, not a SPA — full page
// reloads are fine and they keep every framework in a known state.
//
// If a host needs Turbo, gate it on a body class so it skips EA
// pages: `if (!document.body.classList.contains('ea')) import('@hotwired/turbo');`
// Stimulus controllers from polysource/widgets, /search, /bulk-async
// are auto-registered via the bundles' AssetMapper paths once Phase F lands.
