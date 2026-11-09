/*
 * The controllers of the panel in one import - in place of registerControllers() in the application's Stimulus
 * bootstrap:
 *
 *     import '@byfareska/crud/panel.js';
 *
 * They run in a Stimulus application of their own, next to the application's, which keeps the controllers of Symfony
 * UX its recipes registered (live, autocomplete, chart...): those are not registered here again. Themes drawn with
 * native <dialog> and <details> (daisyui, tailwind) need nothing else; bootstrap5 imports panel.bootstrap5.js
 * instead. The application calls nothing; to register them into an application of its own, with options
 * (except, prefix), it imports registerControllers() from '@byfareska/crud' instead of this file.
 */
import { Application } from '@hotwired/stimulus';
import { registerControllers } from './index.js';

const panel = Application.start();
registerControllers(panel);

export default panel;
