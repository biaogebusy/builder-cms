Drupal Commerce
===============
[![Build Status](https://git.drupalcode.org/project/commerce/badges/3.0.x/pipeline.svg)](https://git.drupalcode.org/project/commerce/-/pipelines)

Drupal Commerce is the leading flexible eCommerce solution for Drupal,
powering over 60,000 online stores of all sizes.

Please report bugs in the [issue queue](https://www.drupal.org/project/issues/commerce?version=any_3.).

[Documentation](http://docs.drupalcommerce.org)

[Issue Tracker](https://www.drupal.org/project/issues/commerce?version=any_3.)

## Installation

Use [Composer](https://getcomposer.org/) to get Drupal + Commerce with all dependencies.

```
composer create-project drupalcommerce/project-base mysite --stability dev --no-interaction
```

See the [install documentation](https://docs.drupalcommerce.org/commerce2/developer-guide/install-update/installation) for more details.

## Disabling Partner banners

Drupal Commerce modules occasionally link to offers from technology partners in
contextually relevant portions of the administrative interface. To simplify
disabling these, the project has established a pattern of all such banners
respecting a setting you can set in your site's settings.php:

`$settings['commerce_show_partner_banners'] = FALSE;`
