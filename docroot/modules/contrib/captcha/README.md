# CAPTCHA

A CAPTCHA is a challenge-response test most often placed within web forms to
determine whether the user is human. The purpose of CAPTCHA is to block form
submissions by spambots, which are automated scripts that post spam content
everywhere they can. The CAPTCHA module provides this feature to virtually any
user facing web form on a Drupal site.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/captcha)

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/captcha)


## Table of contents

- Requirements
- Conflicts/Known issues
- Installation
- Configuration
- Development
- Maintainers


## Requirements

This module requires no modules outside of Drupal core.


## Conflicts/Known issues

CAPTCHA and page caching do not work together currently. However, the CAPTCHA
module does support the Drupal core page caching mechanism: it just disables the
caching of the pages where it has to put its challenges.

If you use other caching mechanisms, it is possible that CAPTCHA's won't work,
and you get error messages like 'CAPTCHA validation error: unknown CAPTCHA
session ID'.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

The configuration page is at `admin/config/people/captcha`,
where you can configure the CAPTCHA module
and enable challenges for the desired forms.
You can also tweak the image CAPTCHA to your liking.

1. Navigate to Administration > Extend and enable the module.
1. Navigate to Administration > Configuration > People > Captcha module
   settings to administer how and when Captcha is used.
1. Select the challenge type you want for each of the listed forms.
1. Select "Add a description to the CAPTCHA" to add a configurable
   description to explain the purpose of the CAPTCHA to the visitor.
1. For Default CAPTCHA validation, define how the response should be
   processed by default. Note that the modules that provide the actual
   challenges can override or ignore this.
1. Save configuration.


## Development

You can disable captcha in your local or test environment by adding the
following line to `settings.php`:
```
$settings['disable_captcha'] = TRUE;
```


## Maintainers

- Fabiano Sant'Ana - [wundo](https://www.drupal.org/u/wundo)
- Julian Pustkuchen - [Anybody](https://www.drupal.org/u/Anybody)
- Jakob Perry - [japerry](https://www.drupal.org/u/japerry)
- Rob Loach - [RobLoach](https://www.drupal.org/u/RobLoach)
- soxofaan - [soxofaan](https://www.drupal.org/u/soxofaan)
- Joshua Sedler - [Grevil](https://www.drupal.org/u/Grevil)
- Thomas Frobieter - [thomas.frobieter](https://www.drupal.org/u/thomas.frobieter)
- Lachlan Ennis - [elachlan](https://www.drupal.org/u/elachlan)
- Naveen Valecha - [naveenvalecha](https://www.drupal.org/u/naveenvalecha)
- Andrii Podanenko - [podarok](https://www.drupal.org/u/podarok)

Supporting organizations:

- Chuva Inc. - [Chuva Inc](https://www.drupal.org/chuva-inc)
- webks GmbH - [DROWL.de](https://www.DROWL.de)
