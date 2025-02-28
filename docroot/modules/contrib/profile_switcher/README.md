# Profile Switcher

This module provides a Drush command to switch between installation
profiles found in the `/profiles` directory.

## Use Cases

* Moving an existing site to distribution
* Moving from an abandoned distribution back to a standard
Drupal core installation profile
* Moving from a multi-site installation profile to a new site-specific one.
* etc.

## Warnings

* Switching profiles is *not* a trivial change like switching a theme.
* *Always* test before using on a live site and *Always* have a backup.
* Switching profiles does *not* run the profile's `.install` file.
This will not work for all distributions.
* Modules, themes, and libraries included in a distribution are only available
when using the distribution.Modules, themes, and libraries in `sites/all` are
available for any profile, *but override profile versions even if the
`sites/all` versions are older*.
* Because some modules register absolute paths to files in Drupal's registry
table, you may run into issues when switching profiles even if the version of
the module is the same.
(See [Tips for fixing registry related issues](https://drupal.org/node/1974964)).
* If the Profile Switcher module was included in a
`/profile/[profile_name]/modules` directory and you switch to a profile that
doesn't include it, you will get a page-not-found error after the switch,
and not be able to switch back until adding this module to `sites/all/modules`.

## Usage

```sh
drush switch:profile NEW_PROFILE_MACHINE_NAME
```

## References

What is the difference between an Install Profile and a Drupal Distribution?
See [Creating distributions](https://www.drupal.org/docs/8/distributions/creating-distributions)
for an explanation.
