/*
 * The prebuilt script of the panel (byfareska_crud.assets: builtin, or auto while the application names no entries):
 * the controllers of the bundle as an application loads them (assets/panel.js), plus what an application's own
 * Stimulus has from the recipes of Symfony UX and the builtin assets have to bring along - the live controller of
 * Live Component, the chart controller of UX Chart.js and the autocomplete controller of UX Autocomplete. One copy of
 * @symfony/ux-live-component, which the controllers of the bundle also import (getComponent()).
 *
 * The controllers of Symfony UX are built from the versions this file pins (package.json): build it again when the
 * bundle requires other ones.
 */
import LiveController from '@symfony/ux-live-component';
import ChartController from '@symfony/ux-chartjs';
import AutocompleteController from '@symfony/ux-autocomplete';
import panel from '../../assets/panel.js';

panel.register('live', LiveController);
panel.register('symfony--ux-chartjs--chart', ChartController);
// The fields to search in of relations (filters and forms), once the application has symfony/ux-autocomplete.
panel.register('symfony--ux-autocomplete--autocomplete', AutocompleteController);

window.CrudStimulus = panel;
