/* eslint import/no-unresolved: 0 */
import Backbone from 'backbone';
import Shepherd from 'shepherd.js';
import { offset } from '@floating-ui/dom';
import _ from 'underscore';

// Have to set variable for backbone.
Backbone.$ = jQuery;
Backbone._ = _;

window.Backbone = Backbone;
window.Shepherd = Shepherd;
window.offsetFloating = offset;
