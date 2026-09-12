# CONTENTS OF THE FILE
----------------------

* Introduction
* Requirements
* Recommended modules
* Installation
* Configuration
* Upgrading from 8.x-1.x
* Security
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


# UPGRADING FROM 8.x-1.x
------------------------

3.x rebuilt every validation rule on top of Symfony's Constraint API and
gave each one a new plugin ID. None of the original 8.x-1.x rule plugins
exist in this module anymore under their old IDs, so after a plain upgrade
any rule set (`field_validation.rule_set.*` config) containing rules
exported under 8.x-1.1 breaks with a PluginNotFoundException — the plugin
ID each rule points at no longer resolves, taking down the rule set admin
pages and validation of the entities the rule set is attached to.

To keep existing 8.x-1.1 rule configuration working, enable the
**Field Validation legacy** submodule (`field_validation_legacy`). It ships
the original 8.x-1.x rule plugins unchanged, under their original plugin
IDs, so rule sets exported before the upgrade keep validating exactly as
they did on 8.x-1.1. No config changes are required — rule set entities
(`field_validation_rule_set`) are unchanged between 8.x-1.1 and 3.x.

`field_validation_legacy` is a frozen copy of the 8.x-1.1 code, kept only
for this transition. It receives no new features and no fixes beyond what's
needed to keep it running on supported Drupal core versions. Once existing
rule sets have been rebuilt on the Constraint-API rules, disable it.

For any *new* rules, use the Constraint-API-based rules shipped in the main
`field_validation` module instead — they are the actively maintained set
going forward. `field_validation_legacy` exists for backward compatibility
only; see
[#3377899](https://www.drupal.org/project/field_validation/issues/3377899).


# SECURITY
----------

The "administer field validation rule set" permission is marked restricted
because two bundled rule types execute admin-entered code as part of
validation:

* **Callback constraint** — invokes an admin-entered "Class::method" static
  callable directly against the field value.
* **Expression constraint** — evaluates an admin-entered expression-language
  string against the field value.

Both rule types are gated behind the same permission as ordinary rules like
Length or Regex, so granting it means granting code execution, not just
configuration access. Only give "administer field validation rule set" to
fully trusted roles.


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
