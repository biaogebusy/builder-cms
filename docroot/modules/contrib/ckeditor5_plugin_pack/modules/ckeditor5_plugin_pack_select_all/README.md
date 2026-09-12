# CKEditor 5 Plugin Pack - Select All
This module is a part of CKEditor 5 Plugin Pack. It integrates the official Select all plugin with CKEditor 5 for Drupal 9 and 10.
The select all feature lets you select the entire content using the Ctrl/Cmd+A keystroke or a toolbar button. This way you can clear or copy all the content in one move.
More info and demo available at https://ckeditor.com/docs/ckeditor5/latest/features/select-all.html

## Installation and configuration
- Install CKEditor 5 Plugin Pack `composer require "drupal/ckeditor5_plugin_pack"`
- Enable `CKEditor 5` and `CKEditor 5 Select All`
- Create or edit existing rich text format that uses CKEditor 5 as editor: `/admin/config/content/formats`
- Drag & drop the select all widget from "Available buttons" to the "Active toolbar"

No external dependencies required, the plugin is integrated directly via DLL Builds.
