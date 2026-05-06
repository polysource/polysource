import { startStimulusApp } from '@symfony/stimulus-bundle';

// polysource/filter ships Stimulus controllers under its own assets dir
// (registered by AssetMapper paths) but Symfony Stimulus Bundle only
// auto-discovers controllers from the host's `assets/controllers/`. We
// therefore wire the package controllers explicitly here. Without this
// the EA-bridge filter modal would render flat — the tab-tree data
// attribute is on the modal but no JS reads it.
import filterModalLayoutController from '@polysource/filter/controllers/filter_modal_layout_controller.js';

const app = startStimulusApp();
app.register('polysource--filter-modal-layout', filterModalLayoutController);
