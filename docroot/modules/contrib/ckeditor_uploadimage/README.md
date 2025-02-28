# CKEditor Upload Image

Integrates CKEditor's Upload Image plugin to Drupal. This enables Drupal's
default WYSIWYG text editor capable of uploading images that were dropped or
pasted from clipboard into the editor as inline image. The upload is
implemented in a non-blocking way, so while the image is being uploaded the user
may continue editing the content.

This module uses the settings and features of native DrupalImage CKEditor plugin
for the uploaded images made via drag and drop or clipboard paste.

## Requirements
* Drupal 8.x
* CKEditor module
* [Upload Image](http://ckeditor.com/addon/uploadimage)
* [Upload Widget](http://ckeditor.com/addon/uploadwidget)
* [File Tools](http://ckeditor.com/addon/filetools)
* [Notification](http://ckeditor.com/addon/notification)
* [Notification Aggregator](http://ckeditor.com/addon/notificationaggregator)

## Installation

### Download CKEditor plugins via composer
1. Run `composer require --prefer-dist composer/installers` to ensure that you
have the composer/installers package installed. This package facilitates the
installation of packages into directories other than `/vendor`
(e.g. `/libraries/ckeditor/plugins`) using Composer.

2. Edit your composer.json file. Under the "installer-paths" section add the following:
    ```
    "libraries/ckeditor/plugins/{$name}": ["vendor:ckeditor-plugin"]
    ```
    Note: If you have this line `"libraries/{$name}": ["type:drupal-library"]`, make sure
    to add the `"libraries/ckeditor/plugins/{$name}": ["vendor:ckeditor-plugin"]` before it.
    So it should look like this:
    ```
    "libraries/ckeditor/plugins/{$name}": ["vendor:ckeditor-plugin"],
    "libraries/{$name}": ["type:drupal-library"]
    ```

3. Next tell composer which libraries to install. You have two options:

    **Option 1: Use the [Composer Merge plugin](https://github.com/wikimedia/composer-merge-plugin)
    to include the CKEditor Uploadimage module's composer.libraries.json (recommended)**

    This is the recommended way which is more convenient and also ensures that the
    required libraries will always be updated when the module is updated.

    1. The merging is accomplished by the aid of the
      [Composer Merge plugin](https://github.com/wikimedia/composer-merge-plugin)
      available on GitHub, so from the project directory run:
      `composer require wikimedia/composer-merge-plugin`

    2. Edit the "composer.json" file of your website and under the **"extra": {**
       section add:
       ```
       "merge-plugin": {
         "include": [
           "modules/contrib/ckeditor_uploadimage/composer.libraries.json"
         ]
       },
       ```
       Note: If the `"merge-plugin"` key already exist, just append the following as one 
       of its values:
       ```
        "include": [
          "modules/contrib/ckeditor_uploadimage/composer.libraries.json"
        ]
        ```
       For example:
       ```
       "merge-plugin": {
         "recurse": true,
         "replace": false,
         "merge-extra": false,
         "include": [
           "modules/contrib/ckeditor_uploadimage/composer.libraries.json"
         ]
       },
       ```                     
       From now on, every time the "composer.json" file is updated, it will also read the
       content of "composer.libraries.json" file located at
       `modules/contrib/ckeditor_uploadimage/` and update accordingly.

    **Option 2: Add packages manually**

    1. Under the `"repositories"` section of composer.json, add the following:
        ```
        {
          "type": "package",
          "package": {
            "name": "ckeditor-plugin/uploadimage",
            "version": "4.8.0",
            "type": "drupal-library",
            "extra": {
              "installer-name": "uploadimage"
            },
            "dist": {
              "url": "https://download.ckeditor.com/uploadimage/releases/uploadimage_4.8.0.zip",
              "type": "zip"
            }
          }
        },
        {
          "type": "package",
          "package": {
            "name": "ckeditor-plugin/uploadwidget",
            "version": "4.8.0",
            "type": "drupal-library",
            "extra": {
              "installer-name": "uploadwidget"
            },
            "dist": {
              "url": "https://download.ckeditor.com/uploadwidget/releases/uploadwidget_4.8.0.zip",
              "type": "zip"
            }
          }
        },
        {
          "type": "package",
          "package": {
            "name": "ckeditor-plugin/filetools",
            "version": "4.8.0",
            "type": "drupal-library",
            "extra": {
              "installer-name": "filetools"
            },
            "dist": {
              "url": "https://download.ckeditor.com/filetools/releases/filetools_4.8.0.zip",
              "type": "zip"
            }
          }
        },
        {
          "type": "package",
          "package": {
            "name": "ckeditor-plugin/notification",
            "version": "4.8.0",
            "type": "drupal-library",
            "extra": {
              "installer-name": "notification"
            },
            "dist": {
              "url": "https://download.ckeditor.com/notification/releases/notification_4.8.0.zip",
              "type": "zip"
            }
          }
        },
        {
          "type": "package",
          "package": {
            "name": "ckeditor-plugin/notificationaggregator",
            "version": "4.8.0",
            "type": "drupal-library",
            "extra": {
              "installer-name": "notificationaggregator"
            },
            "dist": {
              "url": "https://download.ckeditor.com/notificationaggregator/releases/notificationaggregator_4.8.0.zip",
              "type": "zip"
            }
          }
        }
        ```

    2. Under the `"require"` section of composer.json, add the following:
    ```
    "ckeditor-plugin/uploadimage": "4.8.0",
    "ckeditor-plugin/uploadwidget": "4.8.0",
    "ckeditor-plugin/filetools": "4.8.0",
    "ckeditor-plugin/notification": "4.8.0",
    "ckeditor-plugin/notificationaggregator": "4.8.0"
    ```

4. Run `composer require drupal/ckeditor_uploadimage -vvv` to install this module.

### Download CKEditor plugins manually
1. Download the following CKEditor plugins:
    * [Upload Image](http://ckeditor.com/addon/uploadimage)
    * [Upload Widget](http://ckeditor.com/addon/uploadwidget)
    * [File Tools](http://ckeditor.com/addon/filetools)
    * [Notification](http://ckeditor.com/addon/notification)
    * [Notification Aggregator](http://ckeditor.com/addon/notificationaggregator)
2. Unzip and place the contents for each plugin in the the following directory:
    * `DRUPAL_ROOT/libraries/ckeditor/plugins/PLUGIN_NAME`

### Install the module
1. If you need media support, enable core's "Media" module.
2. Install the module per normal
https://www.drupal.org/documentation/install/modules-themes/modules-8.
3. Go to the 'Text formats and editors' configuration page:
`/admin/config/content/formats`, and for each text format/editor combo under the
CKEditor plugin settings, select Image and there you can control the upload
directory, maximum file size, image dimension and turn on/off the image upload 
and turn on/off saving uploaded image as media entity image.
4. Under Enabled filters, "Restrict images to this site" must be disabled.
5. If "Limit allowed HTML tags and correct faulty HTML" is enabled, make sure
the `<img>` tag is included in "Allowed HTML tags" with attributes
`data-cke-saved-src data-cke-upload-id data-widget data-cke-widget-keep-attr
data-cke-widget-data class` inside it.
6. Enable the 'Use the CKEditor Upload Image' permission to applicable Roles at
'Permissions' page: `/admin/people/permissions`.

#### Support
Please use the issue queue for filing bugs with this module at
https://www.drupal.org/project/issues/ckeditor_uploadimage
