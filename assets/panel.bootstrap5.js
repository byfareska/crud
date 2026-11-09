/*
 * The controllers of the panel for the "bootstrap5" theme in one import: panel.js and Bootstrap's modals, drawers
 * (offcanvas) and menus (dropdown), which the controllers open through useBootstrap().
 *
 *     import '@byfareska/crud/panel.bootstrap5.js';
 *
 * Bootstrap comes from the application (npm install bootstrap).
 */
import Dropdown from 'bootstrap/js/dist/dropdown';
import Modal from 'bootstrap/js/dist/modal';
import Offcanvas from 'bootstrap/js/dist/offcanvas';
import { useBootstrap } from './index.js';
import panel from './panel.js';

useBootstrap({ Modal, Offcanvas, Dropdown });

export default panel;
