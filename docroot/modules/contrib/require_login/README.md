# Require Login

Provides catch-all solution to easily require user authentication on all pages. Quick to configure and fully
compatible with any other access control systems. Integrates with the Drupal condition plugin system for granular
access control based on any of the default or installed plugins. See configuration page for additional options.

## Usage

1. Download and install the `drupal/require_login` module. Recommended install method is composer:
   ```
   composer require drupal/require_login
   ```
2. Go to /admin/config/people/login-requirements to configure.
3. Set configuration and save changes.

## Route name conditions

Install https://www.drupal.org/project/route_condition module to set requirements using route names. Otherwise,
use the Request Path condition provided out of the box.
