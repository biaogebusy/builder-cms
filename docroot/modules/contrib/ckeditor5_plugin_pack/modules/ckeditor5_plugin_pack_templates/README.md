# CKEditor 5 Plugin Pack - Templates
This module adds Templates plugin for CKEditor 5 in Drupal 9 and 10.

The template feature allows you to insert predefined content structures into the document. Templates can provide both smaller portions of content (like a formatted table) and base structures for entire documents (for example, a formal letter template). Templates are a useful tool to speed up the writing process and maintain compliance with the company’s document and content policy.
More info and demo available at https://ckeditor.com/docs/ckeditor5/latest/features/template.html

Templates plugin is premium feature available as a part of a CKEditor 5 Productivity Pack. For Drupal users it is available for free. You can learn more about Productivity Pack [here](https://ckeditor.com/docs/ckeditor5/latest/features/productivity-pack.html)

## CKEditor 5 premium features users

In case you already have a license key for CKEditor 5 (without Templates feature) and would like to use this module, please contact our support team. Your license key will need to be updated in order to use the Templates feature.

## Installation and configuration
- Install CKEditor 5 Plugin Pack `composer require "drupal/ckeditor5_plugin_pack"`
- Enable `CKEditor 5` and `CKEditor 5 Templates`
- Create or edit existing rich text format that uses CKEditor 5 as editor: `/admin/config/content/formats`
- Drag & drop the "Insert Template" widget from "Available buttons" to the "Active toolbar"
- Add your own custom templates at `/admin/config/ckeditor5-premium-features/productivity-pack/content-templates`. The detailed instruction is available [here](https://www.drupal.org/docs/contributed-modules/ckeditor-5-premium-features/how-to-install-and-set-up-the-module#s-templates)

No external dependencies required, the plugin is integrated directly via DLL Builds.
