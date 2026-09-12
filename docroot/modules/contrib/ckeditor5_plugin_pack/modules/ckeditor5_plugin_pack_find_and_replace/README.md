# CKEditor 5 Plugin Pack - Find and replace
This module adds Find and Replace plugin for CKEditor 5 in Drupal 9 and 10.

The plugin allows to quickly find a desired phrase within the edited content and optionally replace it. The widget allows
to perform search with "Match case" and "Whole words only", additionally it allows to replace all found occurrences with
single click.
More info and demo available at https://ckeditor.com/docs/ckeditor5/latest/features/find-and-replace.html

## Installation and configuration
- Install CKEditor 5 Plugin Pack `composer require "drupal/ckeditor5_plugin_pack"`
- Enable `CKEditor 5` and `CKEditor 5 Find and replace`
- Create or edit existing rich text format that uses CKEditor 5 as editor: `/admin/config/content/formats`
- Drag & drop the "Find And Replace" widget from "Available buttons" to the "Active toolbar"

No external dependencies required, the plugin is integrated directly via DLL Builds.

## Attribution
This module is based on the [CKEditor Find/Replace](https://www.drupal.org/project/ckeditor_find) maintained by:
- Daniel Hansen ([dhansen](https://www.drupal.org/u/dhansen))
- Shawn Duncan ([FatherShawn](https://www.drupal.org/u/fathershawn))
- Kevin Finkenbinder ([kwfinken](https://www.drupal.org/u/kwfinken))
