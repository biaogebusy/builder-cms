### CKEditor Line Height

Integrates CKEditor's [Line Height plugin](https://ckeditor.com/cke4/addon/lineheight) to Drupal's CKEditor implementation adding a new dropdown button to modify the line height of your content using inline style.

### Requirements

CKEditor Module (Core)
This module also requires the CKEditor "Line Height" plugin.

### Installing dependencies via Composer's Merge Plugin plugin

The Ckeditor Line Height module come shipped with a "composer.libraries.json" file that contains information about all up-to-date libraries required by the module itself. Use this file to install all libraries by merging the "composer.libraries.json" with the "composer.json" file of our Drupal website.

1) The merging is accomplished by the aid of the Composer Merge Plugin plugin available on GitHub, so from the project directory, open a Git bash and run:
composer require wikimedia/composer-merge-plugin

2) Edit the "composer.json" file of your website and under the `"extra":`` { section add:

```
"merge-plugin": {
    "include": [
        "web/modules/contrib/ckeditor_lineheight/composer.libraries.json"
    ]
},
```

From now on, every time the `composer.json` file is updated, it will also read the content of `composer.libraries.json` file located at `web/modules/contrib/ckeditor_lineheight/` and update accordingly.

3) In order for the `composer.json` file to install all the libraries mentioned inside the `composer.libraries.json`, from the Git bash Run: `composer install`

This method will assure that all the libraries will be automatically updated once the `composer.libraries.json` is updated with new versions of the Ckeditor Line Height module.

### Alternatively add the repository directly to your composer.json

If you don't want to use Composer Merge Plugin plugin you may add the repository directly to your `composer.json` file. Make shure to update that manually if ckeditor/lineheight library got updated.

1. Add ckeditor/lineheight repositories to your `composer.json`.

```
"repositories": [
    {
        "type": "package",
        "package": {
            "name": "ckeditor/lineheight",
            "version": "1.04",
            "type": "drupal-library",
            "dist": {
                "url": "https://download.ckeditor.com/lineheight/releases/lineheight_1.0.4.zip",
                "type": "zip"
            }
        }
    }
],
```

2. Execute `composer require ckeditor/lineheight`
3. Make sure there is the file `libraries/lineheight/plugin.js`.

### HOW TO INSTALL DEPENDENCIES MANUALLY:

1. Download the plugin on the project page : https://ckeditor.com/addon/lineheight
2. Create a libraries folder in your drupal root if it doesn't exist.
3. Extract the plugin archive in the libraries folder.
4. Make sure there is the file `libraries/lineheight/plugin.js`.

### HOW TO USE

1. Go to the format and editor config page and click configure on the format your want to edit: `/admin/config/content/formats`
2. Add the plugin buttons in your editor toolbar.

The line heights are predefined, however, you can easily change that setting by implementing `hook_editor_js_settings_alter` for each format like so:

```php
/**
 * Implements hook_editor_js_settings_alter().
 */
function HOOK_editor_js_settings_alter(array &$settings) {
  $settings['editor']['formats']['full_html']['editorSettings']['line_height'] = '10px;22px';
}
```
Above I'm setting the 'full_html' format to have only the 10px and 22px line height options.
Please note that you can change the measuring unit from px to em for example.
