IMPORTANT CHANGES IN 3.x BRANCH
-------------------------------

* Since Drupal 10.1.0, limiting the text formats per field instance is a feature
  provided by Drupal core. Read https://www.drupal.org/node/3318572 for details.
* In the 3.x branch of this module this feature has been removed as obsolete,
  but the module provide an update path from existing sites to move the allowed
  formats, as they were stored by the previous versions of the module, to Drupal
  >=10.1.0 way, in field settings.
* The module provides also a feature that allows site builders to hide the
  formatted text format help and guidelines. Even this feature is still
  preserved in the 3.x module branch, there is an issue that aims to move it in
  Drupal core in the future. See https://www.drupal.org/i/3323007.
