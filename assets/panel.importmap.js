/*
 * The script of the panel in an application of AssetMapper and StimulusBundle that names no entries of its own
 * (byfareska_crud.assets: auto) - loaded by the panel itself, nothing to configure: the Stimulus application of
 * StimulusBundle with the controllers of the application's assets/controllers.json (those the recipes of Symfony UX
 * put there - live, autocomplete, Turbo... - in the versions the application has), plus the controllers of the bundle.
 * The application's own entry (app.js and its styles) is not loaded.
 */
import { startStimulusApp } from '@symfony/stimulus-bundle';
import { registerControllers } from './index.js';

const panel = startStimulusApp();
registerControllers(panel);

window.CrudStimulus = panel;

