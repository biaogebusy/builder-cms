# CONTENTS OF THE FILE
----------------------

* Introduction
* Requirements
* Recommended modules
* Installation
* Configuration
* Developer notes
* Maintainers


# INTRODUCTION
--------------

The Field validation module allows you to specify validation rules for
field instances. This module adds an extra tab to each field instance,
allowing you to specify validation rules for your field instances.

  * For a full description of the module, visit the project page:
    https://drupal.org/project/field_validation

  * To submit bug reports and feature suggestions, or to track changes:
    https://drupal.org/project/issues/field_validation


# REQUIREMENTS
--------------

This module doesn't have any requirements.


# RECOMMENDED MODULES
---------------------

* Clientside validation (http://drupal.org/project/clientside_validation)
  This module adds clientside validation (aka "Ajax form validation")
  for all forms and webforms using jquery.validate.


# INSTALLATION
--------------

* Install as you would normally install a contributed Drupal module. Visit:
  https://www.drupal.org/node/1897420
  for further information.


# CONFIGURATION
---------------

Go to Home >> Administration >> Structure >> Field Validation.
Click on "Add field validation rule set" button. Select the entity type and
bundle of the field which you want to apply the validation.


# DEVELOPER NOTES
-----------------

Validators are plugins, you can program your own validator or extend some of
the existing ones. For more information about the Plugin API see:
https://www.drupal.org/docs/8/api/plugin-api/plugin-api-overview


# MAINTAINERS
-------------

Current maintainers:

* manuel.adan (Manuel Adan) - https://www.drupal.org/u/manueladan

* g089h515r806 (Howard Ge) - https://www.drupal.org/u/g089h515r806

This project has been sponsored by:

* Think in Drupal visit http://www.thinkindrupal.com/ for more information.
* Token and basic conditional support was develped for sigmaxim.com
* Date validation module was sponsored by cgdrupalkwk.
