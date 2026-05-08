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
import filterModalLayoutController from '@polysource/filter/controllers/filter_modal_layout_controller.js';
import cmdkController from '@polysource/search/controllers/cmdk_controller.js';
import progressController from '@polysource/bulk-async/controllers/progress_controller.js';

const app = startStimulusApp();
app.register('polysource--filter-modal-layout', filterModalLayoutController);
app.register('polysource--search--cmdk', cmdkController);
app.register('polysource--bulk-async--progress', progressController);
