CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

The Allowed Formats module limits which text formats are available for each
field instance.

 * For a full description of the module visit:
   https://www.drupal.org/project/allowed_formats

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/allowed_formats


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install the Allowed Formats module as you would normally install a
   contributed Drupal module. Visit https://www.drupal.org/node/1897420 for
   further information.


CONFIGURATION
-------------

There is no real configuration necessary. Navigate to Administration >
Extend and enable the module.

There is now a set of checkboxes for field settings (not the widget settings on
the form display tab) of text fields with a list of allowed formats: Basic
HTML, Restricted HTML, Full HTML, and Plain text.

Note that base fields defined by the entity type (for example the description
field of taxonomy terms) cannot have their allowed formats limited through the
UI.


MAINTAINERS
-----------

 * Florian Loretan (floretan) - https://www.drupal.org/u/floretan

Supporting organization:

 * Wunder - https://www.drupal.org/wunder-group
