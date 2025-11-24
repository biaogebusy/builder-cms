# JS Cookie


Provides a Drupal library definition for the
[JavaScript Cookie](https://github.com/js-cookie/js-cookie) library (js-cookie)
after it was
[deprecated in Drupal 10 and removed from Drupal 11](https://www.drupal.org/node/3322720).


## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers


## Requirements

The module is using a single file (`js.cookie.min.js`) from
[JavaScript Cookie](https://github.com/js-cookie/js-cookie)
as a library.

### Install library manually

You can host the file locally, and respect user privacy, by downloading and
placing it like this. Place in `libraries` folder, same level as `themes` and `modules`:

`/web/libraries/js-cookie/dist/js.cookie.min.js`

If that folder isn't found, a CDN will be used as fallback, and serve the file.

This one-liner downloads the file and creates the needed directories, if they don't exist. Assuming the standard `drupal/recommended-project` structure, using a `web`-folder:

```
curl --create-dirs -o web/libraries/js-cookie/dist/js.cookie.min.js https://cdn.jsdelivr.net/npm/js-cookie@3.0.5/dist/js.cookie.min.js
```

### Install library via composer (Merge Plugin)

If you are using the Composer Merge Plugin you can add the composer.libraries.json to your project's composer.json:

```
"extra": {
  "merge-plugin": {
    "include": [
      "web/modules/contrib/js_cookie/composer.libraries.json"
    ]
  }
}
```

Then use the following command:

`composer update 'js_cookie/js_cookie'`

For more information see [How to use composer to install libraries for the Webform module](https://www.drupal.org/node/3003140), which takes a similar approach to using composer.libraries.json.

### Install library via composer (Asset Packagist)

If you are using the Asset Packagist and has already prepared your composer.json, you can use the following command:

`composer require npm-asset/js-cookie:3.0.5`

See [Downloading third-party libraries using Composer](https://www.drupal.org/docs/develop/using-composer/manage-dependencies#third-party-libraries) for more information.

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

- If using this in a custom module, run `composer require drupal/js_cookie` from your project root.
- If using this in a contributed module, make sure to list `js_cookie:js_cookie` in your module's `.info.yml` file dependencies. 
- If your contributed module also has a manual `composer.json` file, make sure to add a require dependency on `"drupal/js_cookie": "^1.0"`.
- Replace any of your `.libraries.yml` dependencies on `core/js-cookie` with `js_cookie/js-cookie`. 


## Maintainers

- Dave Reid - [Dave Reid](https://www.drupal.org/u/dave-reid)
