# CKEditor 5 Plugin Pack - WProofreader free
This module is a part of CKEditor 5 Plugin Pack. It integrates the WProofreader plugin created by WebSpellChecker LLC. with CKEditor 5 for Drupal 9 and 10.

WProofreader SDK is an AI-driven, multi-language text correction tool. Spelling, grammar, and punctuation suggestions appear on hover as you type or in a separate dialog aggregating all mistakes and replacement suggestions in one place.
More info and demo available at https://ckeditor.com/docs/ckeditor5/latest/features/spelling-and-grammar-checking.html

This module provides a free version of WProofreader which has some limitations over the paid version. For more information about limitations see {link_placeholder}

In case you would like to use paid version please uninstall this module and enable the "CKEditor 5 Premium Features WProofreader" module.
The configuration guide for paid version is available [here](https://www.drupal.org/docs/contributed-modules/ckeditor-5-premium-features/how-to-install-and-set-up-the-module#s-wproofreader-spelling-and-grammar-checker-configuration)

## Installation and configuration
- Install CKEditor 5 Plugin Pack `composer require "drupal/ckeditor5_plugin_pack"`
- Enable `CKEditor 5` and `CKEditor 5 WProofreader free`
- Create or edit existing rich text format that uses CKEditor 5 as editor: `/admin/config/content/formats`
- Drag & drop the "WProofreader text checker" widget from "Available buttons" to the "Active toolbar"

No external dependencies required, the plugin is integrated directly via DLL Builds.
