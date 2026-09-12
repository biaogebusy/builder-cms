# Tour

The Tour module provides guided tours of the site interface via tooltips.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/tour).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/tour).

**Disclaimer: Due to Tours being config and possible to be edited on a site
basis we will not be doing any Tour or Tip updates retroactively.**

## Table of contents (optional)

- Requirements
- Installation
- Configuration
- Extension modules
- Recipes
- Maintainers
- Shout-outs

## Requirements (required)

This module requires no modules outside of Drupal core.

This module is stuck on shepherd v13 due to their licensing agreement
[License](https://github.com/shipshapecode/shepherd?tab=License-1-ov-file#readme)

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules)

## Configuration

* Enabling the module
  * Enable the module at `Administration > Extend`.
  * Profit.
* Adding Tours
  * To add tours, add them in a custom module's `config/optional` folder.
  * Naming should be in the style `tour.tour.unique-id.yml`
  * You can also add tours in the Drupal admin UI using the Tour UI module.
    These tours are saved in the site config.
* Configuring Tours
  * You can add a tour to any route available in Drupal.
  * Some example are:
    * *All node add/edit forms (Recommended to do both)*
      `entity.node.add_form`
      `entity.node.edit_form`
    * *All front end nodes*
      `entity.node.canonical`
    * *Taxonomy vocabulary overview form*
      `entity.taxonomy_vocabulary.overview_form`
    * *Taxonomy term add/edit forms (Recommended to do both)*
      `entity.taxonomy_term.add_form`
      `entity.taxonomy_term.edit_form`
* Adding additional route parameters
  * The primary hook of this module, `tour_enhancements_page_bottom`  in
    tour_enhancements.module adds the availability of "bundle" type to
    entity.node.canonical, so you can add tours to all article nodes.  You can
    also add additional if statements around line 33 to check for things like
    if it has a certain field, or if it is published.
* Viewing Tours
  * If the user has the permission to view the Admin Toolbar, and the permission
    to view tours, a button will appear in the top right of the toolbar.
  * Tours can also be viewed to those with permissions to view tours by adding
    the `?tour` parameter to the url the tour should appear on based on the
    route.

## Notes

- Tour ships with a hot key feature that triggers with 'alt' + 't'.
- It has been discovered that Shepherd allows for previous/next Tour tips using
  the left and right arrow keys.

## Extension Modules

- [Tour Extras](https://www.drupal.org/project/tour_extras)
  - Provides a formatted text tip plugin that extends the standard text tip with
    WYSIWYG editing capabilities.
  - Provides a URL step tip plugin that removes the last step of a tour and
    hooks into the preceding step's "Next" button action, to redirect to a
    specified url.

## Recipes

- [Tour Core](https://www.drupal.org/project/tour_core)
- [Tour Core Language](https://www.drupal.org/project/tour_core_language)

## Maintainers

- Stephen Mustgrave - [smustgrave](https://www.drupal.org/u/smustgrave)

## Shout-outs

- Jim Birch - [thejimbirch](https://www.drupal.org/u/thejimbirch)
  - For contributing [Tour Enhancements](https://www.drupal.org/project/tour_enhancements)
    to be part of the Tour module.
