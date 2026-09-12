# CKEditor 5 Plugin Pack - Text transformation
This module is a part of CKEditor 5 Plugin Pack. It integrates the official Automatic text transformation (autocorrect) plugin with CKEditor 5 for Drupal 9 and 10.
The text transformation feature enables autocorrection. It automatically changes predefined text fragments into their improved forms.
More info and demo available at https://ckeditor.com/docs/ckeditor5/latest/features/text-transformation.html

## Installation and configuration
- Install CKEditor 5 Plugin Pack `composer require "drupal/ckeditor5_plugin_pack"`
- Enable `CKEditor 5` and `CKEditor 5 Text transformation`
- Create or edit existing rich text format that uses CKEditor 5 as editor: `/admin/config/content/formats`
- Enable the plugin in the CKEditor 5 plugin settings section.
- Optionally you can define your own transformation rules and/or disable rules provided by the plugin as default.

No external dependencies required, the plugin is integrated directly via DLL Builds.
