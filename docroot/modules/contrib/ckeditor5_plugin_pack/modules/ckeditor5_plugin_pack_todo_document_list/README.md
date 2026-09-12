# CKEditor 5 Plugin Pack - To-do list
This module is a part of CKEditor 5 Plugin Pack. It integrates the official To-do list plugin with CKEditor 5 for Drupal 10.
The to-do list feature lets you create a list of interactive checkboxes with labels. It supports all features of bulleted and numbered lists, so you can nest a to-do list together with any combination of other lists.
More info and demo available at https://ckeditor.com/docs/ckeditor5/latest/features/lists/todo-lists.html

## Installation and configuration
- Install CKEditor 5 Plugin Pack `composer require "drupal/ckeditor5_plugin_pack"`
- Enable `CKEditor 5` and `CKEditor 5 To-do List` (the module requires Drupal 10.2 or higher)
- Create or edit existing rich text format that uses CKEditor 5 as editor: `/admin/config/content/formats`
- Drag & drop the "Todo List" widget from "Available buttons" to the "Active toolbar"

No external dependencies required, the plugin is integrated directly via DLL Builds.
