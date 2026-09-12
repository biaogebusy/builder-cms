# CKEditor 5 Plugin Pack - Page Break
This module is a part of CKEditor 5 Plugin Pack. It integrates the official Page break plugin with CKEditor 5 for Drupal 9 and 10.
The page break feature lets you insert page breaks into your content. This gives you more control over the final structure of a document that is printed or exported to PDF or Word.
More info and demo available at https://ckeditor.com/docs/ckeditor5/latest/features/page-break.html

## Installation and configuration
- Install CKEditor 5 Plugin Pack `composer require "drupal/ckeditor5_plugin_pack"`
- Enable `CKEditor 5` and `CKEditor 5 Page Break`
- Create or edit existing rich text format that uses CKEditor 5 as editor: `/admin/config/content/formats`
- Drag & drop the page break widget from "Available buttons" to the "Active toolbar"

No external dependencies required, the plugin is integrated directly via DLL Builds.
