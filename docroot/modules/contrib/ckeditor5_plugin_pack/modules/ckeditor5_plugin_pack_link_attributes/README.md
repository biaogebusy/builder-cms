# CKEditor 5 Plugin Pack - Link Attributes
This module is a part of CKEditor 5 Plugin Pack. It integrates the Link Attributes (decorators) with CKEditor 5 for Drupal 9 and 10.
By default, all links created in the editor have the href="..." attribute in the editor data. If you want your links to have additional link attributes, link decorators provide an easy way to configure and manage them.

More info and demo available at https://ckeditor.com/docs/ckeditor5/latest/features/link.html#custom-link-attributes-decorators

## Installation and configuration
- Install CKEditor 5 Plugin Pack `composer require "drupal/ckeditor5_plugin_pack"`
- Enable `CKEditor 5` and `CKEditor 5 Link Attributes`
- Create or edit existing rich text format that uses CKEditor 5 as editor: `/admin/config/content/formats`
- In the settings of the CKEditor 5 plugin, you can configure the link attributes that you want to use in the editor.

No external dependencies required, the plugin is integrated directly via DLL Builds.
