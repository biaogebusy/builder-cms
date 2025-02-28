# About DropzoneJS

This is the Drupal integration for [DropzoneJS](http://www.dropzonejs.com/).

### How to install

#### The non-composer way

1. Download this module
2. [Download DropzoneJS](https://github.com/dropzone/dropzone), use the latest
   dist.zip for either Dropzone 5 or 6 and put it in a libraries/dropzone folder
   so that you have libraries/dropzone/dropzone-min.js
3. Install dropzonejs the [usual way](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules)

You will now have a dropzonejs element at your disposal.

#### The composer way

This assumes that the type:drupal-library is set up to be installed in
web/libraries.

Add a custom package to the root `composer.json` file. Its `repositories` key
looks like the following. Adjust version numbers according to current release.

Dropzone 5:
```
    "repositories": [
        ...
        {
            "type": "package",
            "package": {
                "name": "enyo/dropzone",
                "version": "5.9.3",
                "type": "drupal-library",
                "dist": {
                    "url": "https://github.com/dropzone/dropzone/releases/download/v5.9.3/dist.zip",
                    "type": "zip"
                }
            }
        }
    ]
```

Dropzone 6:
```
    "repositories": [
        ...
        {
            "type": "package",
            "package": {
                "name": "enyo/dropzone",
                "version": "6.0.0-beta.2",
                "type": "drupal-library",
                "dist": {
                    "url": "https://github.com/dropzone/dropzone/releases/download/v6.0.0-beta.2/dist.zip",
                    "type": "zip"
                }
            }
        }
    ]
```

Run `composer require drupal/dropzonejs enyo/dropzone`, the DropzoneJS library
will be installed to the `libraries` folder automatically as well.

To also install exif-js for optional client-resize, define another repository.

```
    {
        "type": "package",
        "package": {
            "name": "exif-js/exif-js",
            "version": "v2.3.0",
            "type": "drupal-library",
            "dist": {
                "type": "zip",
                "url": "https://github.com/exif-js/exif-js/archive/refs/tags/v2.3.0.zip",
            }
        }
    },
```

And require it with `composer require exif-js/exif-js`

### Future plans:
- A dropzonejs field widget.
- Handling already uploaded files.
- Handling other types of upload validations (min/max resolution, min size,...)
- Removing files that were removed by the user on first upload from temp storage.

### Project page:
[drupal.org project page](https://www.drupal.org/project/dropzonejs)

### Maintainers:
+ Janez Urevc (@slashrsm) drupal.org/u/slashrsm
+ John McCormick (@neardark) drupal.org/u/neardark
+ Primoz Hmeljak (@primsi) drupal.org/u/Primsi
+ Qiangjun Ran (@jungle) drupal.org/u/jungle

### Get in touch:
 - http://groups.drupal.org/media
 - **#media**: http://drupal.slack.com

### Thanks:
 The development of this module is sponsored by [Examiner.com](http://www.examiner.com)
 Thanks also to [NYC CAMP](http://nyccamp.org/) that hosted media sprints.
