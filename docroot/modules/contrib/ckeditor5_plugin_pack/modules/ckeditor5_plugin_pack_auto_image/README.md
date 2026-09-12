# CKEditor 5 Plugin Pack - Auto Image
This module is a part of CKEditor 5 Plugin Pack. It integrates the official Auto Image plugin with CKEditor 5 for Drupal 9 and 10.
The AutoImage plugin recognizes image links in the pasted content and embeds them shortly after they are injected into the document to speed up the editing.
Accepted image extensions are: jpg, jpeg, png, gif, and ico.
More info and demo available at https://ckeditor.com/docs/ckeditor5/latest/features/images/images-inserting.html#inserting-images-via-pasting-a-url-into-the-editor

## Installation and configuration
- Install CKEditor 5 Plugin Pack `composer require "drupal/ckeditor5_plugin_pack"`
- Enable `CKEditor 5` and `CKEditor 5 Auto Image`
- Create or edit existing rich text format that uses CKEditor 5 as editor: `/admin/config/content/formats`
- Enable the plugin in the CKEditor 5 plugin settings section.

No external dependencies required, the plugin is integrated directly via DLL Builds.
