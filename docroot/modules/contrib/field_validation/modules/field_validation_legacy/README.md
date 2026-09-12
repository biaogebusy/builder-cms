# FIELD VALIDATION LEGACY

This submodule exists purely for backward compatibility. It ships the
original 8.x-1.x Field Validation rule plugins, unchanged, under their
original plugin IDs.

Enable it if you're upgrading a site from field_validation 8.x-1.1 to 3.x
and want your existing rule sets (`field_validation.rule_set.*` config) to
keep working without edits. See the main module's README ("Upgrading from
8.x-1.x") for details.

Do not use these plugins for new validation rules — use the Constraint-API
rules in the main `field_validation` module instead. This submodule is a
frozen copy of the 8.x-1.1 code: no new features, no fixes beyond keeping
it running on supported Drupal core versions. Disable it once existing
rule sets have been rebuilt on the Constraint-API rules.

Origin: https://www.drupal.org/project/field_validation/issues/3377899
