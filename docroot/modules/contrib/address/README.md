# Address

Provides functionality for storing, validating and displaying international
postal addresses.

The Drupal 8 heir to the addressfield module, powered by the
[commerceguys/addressing](https://github.com/commerceguys/addressing) library.

## Installation
Since the module requires an external library, Composer or Ludwig must be used.

### Composer
If your site is [managed via Composer](https://www.drupal.org/node/2718229), use
Composer to download the module, which will also download the required library:

   ```sh
   composer require "drupal/address ~2.0"
   ```
~2.0 downloads the latest release, use 2.x-dev to get the -dev release instead.
Use ```composer update drupal/address --with-dependencies``` to update to a new
release.

### Ludwig
Composer is recommended whenever possible. However, if you are not familiar with
Composer yet (or you want to avoid it for other reasons) you can install and use
[Ludwig](https://www.drupal.org/project/ludwig) module to manage Address module
library dependencies.

Read more at Ludwig Installation and Usage guide:
https://www.drupal.org/docs/contributed-modules/ludwig/installation-and-usage
