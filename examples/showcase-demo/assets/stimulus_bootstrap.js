import { startStimulusApp } from '@symfony/stimulus-bundle';

// Polysource packages ship Stimulus controllers under each bundle's
// own `assets/` dir (registered as AssetMapper paths by the bundles'
// PrependExtensionInterface). Symfony Stimulus Bundle only
// auto-discovers controllers from the host's `assets/controllers/`,
// so we wire the package controllers explicitly here.
//
// Without these registrations, the matching `data-controller="..."`
// markup mounts in the DOM but the Stimulus app never imports the
// controller — the feature renders structurally but is JS-dead.
//
// v0.2.0 note: the `polysource--filter-modal-layout` controller was
// removed when the bridge's filter tabs/groups moved to server-side
// `<details name>` rendering (cf. ADR-027). The `polysource--filter-subpanel`
// controller was also removed in the same release for the same
// reason. No JS registration is needed for either feature now.
import cmdkController from '@polysource/search/controllers/cmdk_controller.js';
import progressController from '@polysource/bulk-async/controllers/progress_controller.js';
import rowDetailsController from '@polysource/filter/controllers/row_details_controller.js';

const app = startStimulusApp();
app.register('polysource--search--cmdk', cmdkController);
app.register('polysource-bulk-progress', progressController);
app.register('polysource--row-details', rowDetailsController);
