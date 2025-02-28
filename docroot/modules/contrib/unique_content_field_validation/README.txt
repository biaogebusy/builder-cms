Unique Content Field Validation

## CONTENTS OF THIS FILE

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


## INTRODUCTION

This module allows you to require that the content supplied
for entity fields, node titles or taxonomy terms names will
unique if so configured in each field or title/name of
the entity, allowing the administrator to set a validation
message in case the filled-in value already exists.

There is another option if you configure a field to be
of multiple cardinality, with this feature, you can prevent
the same value to be entered more than once within the same
field and configure an error message as well if you want to.


 * For a full description:
   https://drupal.org/project/unique_content_field_validation

 * Issue queue for Unique Content Field Validation:
   https://drupal.org/project/issues/unique_content_field_validation

## REQUIREMENTS

 * This module does not have any dependency.


## INSTALLATION

  * Install as you would normally install a contributed drupal module. See:
    https://www.drupal.org/docs/extending-drupal/installing-modules
    for further information.

## CONFIGURATION

  * Once the module is enabled, you will see a settings group in each
  content type / vocabulary called "Unique". Enable the settings
  checking the checkbox "Unique" and set a validation message if
  you want to. This restricts the entity title/name within that bundle.
  * In each field attached to some entity you can also find a
  settings group called "Unique". Enable the settings checking the checkbox
   "Unique" and set a validation message if you want to. This restricts
   the field value within that entity allowing to a unique values.
  * Also in each field you can find a checkbox called "Do not allow same value",
  enable this and set a error message with the provided tokens in the
  description if you don't want to use the default one.


## MAINTAINERS

Current maintainers:
 * Fabian Sierra (fabiansierra5191) - https://www.drupal.org/u/fabiansierra5191
