CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration


INTRODUCTION
------------

The CKEditor Font Size and Family module enables the CKEditor Font Size and
Family plugin (https://ckeditor.com/cke4/addon/font) in your WYSIWYG editor.

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


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.

This module requires the CKEditor font plugin (http://ckeditor.com/addon/font).


INSTALLATION
------------

Local installation (non-composer):

    1. Download the CKEditor font plugin (v4.13.x to be compatible with Drupal
       9) from http://ckeditor.com/addon/font.
    2. Place the plugin in the root libraries folder (/libraries or 
      /libraries/ckeditor/plugins).
    3. Install as you would normally install a contributed Drupal module. Visit
       https://www.drupal.org/node/895232/ for further information.

Composer installation:

    1. CKEditor Font Size and Family's composer.json will automatically install
       the library into `base_path()/libraries/font`. To add the library,
       type `composer require drupal/ckeditor_font` at your Drupal project root.


CONFIGURATION
-------------

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
    6. Under 'Font sizes', provide a list of approved font sizes:
       `123px|Size label
       123em|Size label
       123%|Size label`
    7. Click 'Save Configuration'.
    8. The Font Family and Font Size buttons will appear in CKEditor modals
       for the configured text filter.
