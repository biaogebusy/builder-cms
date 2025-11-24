# CKEditor Font

CKEditor Font Size & Family enables the [CKEditor Font Size and Family plugin](http://ckeditor.com/addon/font), which adds drop-downs that apply CSS classes or attributes as inline element style.

The list of font styles can be easily customized.

**PLEASE NOTE:** The use of this project is deprecated. For new sites, it is highly recommended to use the [CKEditor5 Plugin Pack](https://www.drupal.org/project/ckeditor5_plugin_pack) module maintained by the CKSource team.

Use this module if you are still using this module in your other sites and want to transition to other modules in the near future.

## Table of contents

 - Introduction
 - Requirements
 - Installation
 - Configuration


## Introduction

The CKEditor Font Size and Family module enables the [CKEditor Font Size and
Family plugin](https://ckeditor.com/cke4/addon/font) in your WYSIWYG editor.

This plugin adds Font Size and Font Family dropdowns that default apply as
inline element styles. The default collection of fonts includes most popular
serif fonts (Times New Roman, Georgia), sans-serif fonts (Arial, Verdana,
Tahoma), and monospaced fonts (Courier New).

The list of font sizes and styles can be easily customized for each text filter.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/ckeditor_font

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/ckeditor_font

 * For additional resources, visit the community documentation:
   https://www.drupal.org/docs/8/modules/ckeditor-font-size-and-family


## Requirements

This module requires no modules outside of Drupal core.

This module requires the [CKEditor font plugin](http://ckeditor.com/addon/font).


## Installation

### Local installation (non-composer):

1. Download the **CKEditor font plugin** (v4.13.x to be compatible with Drupal
   10/11) from http://ckeditor.com/addon/font.

   You can also download the library by using the repository found in the `composer.libraries.yml` file:

   ```
    "cke4-font": {
      "type": "package",
      "package": {
        "name": "cke4/font",
        "version": "4.25.1",
        "type": "drupal-library",
        "extra": {
          "installer-name": "font"
        },
        "dist": {
          "url": "https://download.ckeditor.com/font/releases/font_4.25.1-lts.zip",
          "type": "zip"
        }
      }
    }
    ```

    Add this file in your site project's `composer.json` file and run:

    `composer require "cke4/font"`

    The library will be downloaded in the web/libraries/font directory.
    
2. Place the plugin in the root libraries folder (/libraries).
3. Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/node/895232/ for further information.

### Composer installation:

1. CKEditor Font Size and Family's composer.json will automatically install
   the library into `base_path()/libraries/font`. To add the library,
   type `composer require drupal/ckeditor_font` at your Drupal project root.

## Configuration

1. When enabled, navigate to Administration > Configuration >
   Text formats and editors.
2. Select the filter you want to add the Font functionality to, and click
   'Configure'.
3. From the 'Toolbar configuration', drag the 'f' (font families) and/or
   'S' (font size) buttons from the 'Available buttons' into the
   'Active toolbar'.
4. Configure the options under CKEditor plugin settings > Font Settings.
5. Under 'Font families', provide a list of approved font sizes:
   `Primary font, fallback1, fallback2|Font Label`

   Example:
 
   ```
   Verdana, Geneva, sans-serif
   Lucida Console, Courier New, monospace
   Times New Roman, Times, serif
   ```
6. Under 'Font sizes', provide a list of approved font sizes:
   ```
   123px|Size label
   123em|Size label
   123%|Size label
   ```

   Example
   ```
   10px|Normal Size
   20px|Medium Size
   30px|Whopper Size
   40px|Super Whopper
   123px|Size label
   ```

7. For the Font Background Color (only available for CKEditor5), Enter colors on new lines. Colors must be added with the     following syntax:

   ```
   rgb(255,255,255)|Color
   hsl(0,0%,0%)|Color
   \#ff0000|Color
   ```

8. Click 'Save Configuration'.
9. The Font Family and Font Size buttons will appear in CKEditor modals
   for the configured text filter.
