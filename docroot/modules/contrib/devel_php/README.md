# Devel PHP

The Execute feature has been removed from the Devel module for Drupal 8 since
version 2. This module re-adds back that feature as an external module.

Go to the "Execute PHP Code" page (`/devel/php`), either using the Admin Toolbar
Tools module or using the Devel toolbar (see [Configuration](#configuration)
section) or using the Devel menu.

There is also a block you can place on your page if needed.


## Requirements

This module requires the following modules:
- [Devel](https://www.drupal.org/project/devel)


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

The module has no settings or configuration.

Otherwise, it uses configuration from the Devel module:
- On Devel configuration page (`/admin/config/development/devel`), in
  `Variables Dumper`, you can choose how the output will behave.
- On Devel toolbar configuration page
  (`/admin/config/development/devel/toolbar`), you can enable `Execute PHP`
  menu link.
