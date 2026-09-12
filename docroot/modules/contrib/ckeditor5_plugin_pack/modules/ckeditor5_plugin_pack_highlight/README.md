# CKEditor 5 Plugin Pack - Highlight
This module is a part of CKEditor 5 Plugin Pack. It integrates the official Highlight plugin with CKEditor 5 for Drupal 9 and 10.
The highlight feature lets you mark text fragments with different colors. You can use it both as a marker
(to change the background color) and as a pen (to change the text color).
More info and demo available at https://ckeditor.com/docs/ckeditor5/latest/features/highlight.html

## Installation and configuration
- Install CKEditor 5 Plugin Pack `composer require "drupal/ckeditor5_plugin_pack"`
- Enable `CKEditor 5` and `CKEditor 5 Highlight`
- Create or edit existing rich text format that uses CKEditor 5 as editor: `/admin/config/content/formats`
- Drag & drop the widgets you want to use from "Available buttons" to the "Active toolbar"
- Optionally, specify your own custom markers in the CKEditor 5 plugin settings section. You can also disable the default markers provided by the plugin.

No external dependencies required, the plugin is integrated directly via DLL Builds.

## Attribution
This module is based on the [CKEditor5 Highlight](https://www.drupal.org/project/ckeditor5_highlight) maintained by:
Luhur Abdi Rizal ([el7cosmos](https://www.drupal.org/u/el7cosmos))
