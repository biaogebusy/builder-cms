# CKEditor 5 Plugin Pack - Word count

This module is a part of CKEditor 5 Plugin Pack. It integrates the official Word count and character count plugin with CKEditor 5 for Drupal 9 and 10.
The word count feature lets you track the number of words and characters in the editor. This helps you control the volume of your content and check the progress of your work.
More info and demo available at https://ckeditor.com/docs/ckeditor5/latest/features/word-count.html

## Installation and configuration
- Install CKEditor 5 Plugin Pack `composer require "drupal/ckeditor5_plugin_pack"`
- Enable `CKEditor 5` and `CKEditor 5 Word count`
- Create or edit existing rich text format that uses CKEditor 5 as editor: `/admin/config/content/formats`
- Enable the plugin in the CKEditor 5 plugin settings section and select which counts you would like to be displayed.

No external dependencies required, the plugin is integrated directly via DLL Builds.
