#Installation using Composer (recommended)

If you use Composer to manage dependencies, edit your site's "composer.json"
file as follows.

1. Run `composer require --prefer-dist composer/installers` to ensure that
you have the "composer/installers" package installed. This package
facilitates the installation of packages into directories other than
"/vendor" (e.g. "/libraries") using Composer.

2. Add the following to the "installer-paths" section of "composer.json":

        "libraries/{$name}": ["type:drupal-library"],

3. Add the following to the "repositories" section of "composer.json":

        {
            "type": "package",
            "package": {
                "name": "google/charts",
                "version": "45",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "google_charts"
                },
                "dist": {
                    "url": "https://www.gstatic.com/charts/loader.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        }

4. Run `composer require --prefer-dist google/charts:45` - you should find
that new directories have been created under "/libraries"

**Please note**: if you observe an SSL error when trying to download this
library, you can address this by changing the "url" in the code above to
"http://www.gstatic.com/charts/loader.js" and by adding in:
"secure-http": false
