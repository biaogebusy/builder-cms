# CKEditor 5 Plugin Pack - Font Plugins
This module is a part of CKEditor 5 Plugin Pack. It integrates the official font plugins with CKEditor 5 for Drupal 9 and 10.

## Module contents
The module adds integration of  `FontFamily`, `FontSize`, `FontColor` and `FontBackgroundColor` plugins.
More info and demo available at https://ckeditor.com/docs/ckeditor5/latest/features/font.html

## Installation and configuration
- Install CKEditor 5 Plugin Pack `composer require "drupal/ckeditor5_plugin_pack"`
- Enable `CKEditor 5` and `CKEditor 5 Font Plugins`
- Create or edit existing rich text format that uses CKEditor 5 as editor: `/admin/config/content/formats`
- Drag & drop the widgets you want to use from "Available buttons" to the "Active toolbar"
- Optionally, specify your own custom options in the CKEditor 5 plugin settings section

No external dependencies required, the plugin is integrated directly via DLL Builds.

## Attribution
This module is based on the [CKEditor 5 - Font Plugin (Text Color, Background Color)](https://www.drupal.org/project/ckeditor5_font) maintained by:
- Ivan Sollima ([devicious](https://www.drupal.org/u/devicious))
- [renatog](https://www.drupal.org/u/renatog)
- Daniel Bielke ([dbielke1986](https://www.drupal.org/u/dbielke1986))

That project followed the trails of the old https://www.drupal.org/project/colorbutton project for CKEditor 4.
