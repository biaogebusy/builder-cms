# Profile

The Profile module provides configurable user profiles.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/profile).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/profile).


## Table of contents

- Requirements
- Installation
- Configuration
- Comparison to user account fields
- Features
- Maintainers


## Requirements

This module requires the following modules:

- [Entity API](https://www.drupal.org/project/entity)


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

The module has no menu or modifiable settings. There is no configuration. When
enabled, the module will prevent the links from appearing. To get the links
back, disable the module and clear caches.


## Comparison to user account fields

Why use profiles instead of user account fields?

- With profile, user account settings and user profiles are conceptually different things, e.g. with the "Profile" module enabled users get two separate menu links "My account" and "My profile".
- Profile allows for creating multiple profile types, which may be assigned to roles via permissions (e.g. a general profile + a customer profile)
- Profile supports private profile fields, which are only shown to the user owning the profile and to administrators.

## Features

- Multiple profile types may be created via the UI (e.g. a general profile + a customer profile), whereas the module provides separated permissions for those.
- Optionally, profile forms are shown during user account registration.
- Fields may be configured to be private - thus visible only to the profile owner and administrators.
- Profile types are displayed on the user view page, and can be configured through "Manage Display" on account settings.


## Maintainers

- Bojan Živanović - [bojanz](https://www.drupal.org/u/bojanz)
- Wolfgang Ziegler - [fago](https://www.drupal.org/u/fago)
- Jonathan Sacksick - [jsacksick](https://www.drupal.org/u/jsacksick)
- Matt Glaman - [mglaman](https://www.drupal.org/u/mglaman)
- Pedro Cambra - [pcambra](https://www.drupal.org/u/pcambra)
- Jonathan Daggerhart - [daggerhart](https://www.drupal.org/u/daggerhart)
