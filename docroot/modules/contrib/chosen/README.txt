-- SUMMARY --

  Chosen uses the Chosen jQuery plugin to make your <select> elements
  more user-friendly.


-- INSTALLATION --

  1. Download the Chosen jQuery plugin
     (https://harvesthq.github.io/chosen/ version 1.5 or higher is recommended)
     and extract the file under "libraries".
  2. Download and enable the module.
  3. Configure at Administer > Configuration > User interface > Chosen
     (requires administer site configuration permission)

-- INSTALLATION VIA COMPOSER --
  It is assumed you are installing Drupal through Composer using the Drupal
  Composer facade. See https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies#drupal-packagist

  Before you add the module using composer, you should add an installer path
  so that the Chosen JavaScript library is installed in the correct location.
  You might have an entry similar to below in your composer.json already if
  you had used [drupal-composer/drupal-project](https://github.com/drupal-composer/drupal-project)
  to create your project.
```
    "extra": {
        "installer-paths": {
            "web/libraries/{$name}": ["type:drupal-library"]
        }
    }
```
  where `web/libraries/` is the path to the libraries directory relative to your
  _project_ root. Modify the entry above to add `harvesthq/chosen` in that array.
```
    "extra": {
        "installer-paths": {
            "web/libraries/{$name}": [
              "type:drupal-library",
              "harvesthq/chosen"
            ]
        }
    }
```

  Next, you need to add a custom installer-type so that composer installer
  extended plugin can pick it up. Find the `installer-types` entry in extra
  section and add `library` to it. Your entry should look something like the
  following:
```
    "extra": {
        "installer-types": [
            "library"
        ]
    }
```

  Remember, you may have other entries in there already. For this to work, you
  need to have the 'oomphinc/composer-installers-extender' installer. If you
  don't have it, or are not sure, simply run:
```
composer require oomphinc/composer-installers-extender
```

  Then, run the following composer command:

```
composer require drupal/chosen
```

  This command will add the Chosen Drupal module and JavaScript library to your
  project. The library will be downloaded to the `drupal-library` installer path
  you set in the first step.

-- INSTALLATION VIA DRUSH --

  A Drush command is provided for easy installation of the Chosen plugin.

  drush chosenplugin

  The command will download the plugin and unpack it in "libraries".
  It is possible to add another path as an option to the command, but not
  recommended unless you know what you are doing.

  If you are using Composer to manage your site's dependencies,
  then the Chosen plugin will automatically be downloaded to `libraries/chosen`.

-- ACCESSIBILITY CONCERN --

  There are accessibility problems with the main library as identified here:
        https://github.com/harvesthq/chosen/issues/264

-- TROUBLE SHOOTING --

  How to exclude a select field from becoming a chosen select.
    - go to the configuration page and add your field using the jquery "not"
      operator to the textarea with the comma separated values.
      For date fields this could look like:
      select:not([name*='day'],[name*='year'],[name*='month'])
