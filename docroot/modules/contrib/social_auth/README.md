CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Installation
 * Configuration
 * Support requests
 * Maintainers


INTRODUCTION
------------

This project is part of the [Drupal Social Initiative](https://groups.drupal.org/social-initiative).

Social Auth is part of the Social API. It provides a common interface for
creating modules related to user registration/login through social networks'
accounts.

* This module defines a path `/admin/config/social-api/social-auth` which
  displays a table of implementers (modules to register/login through social
  networks' accounts).

* It also provides a block Social Auth Login which contains links to log in
  users through the enabled social networks' module clients

* Alternatively, site builders can place (and theme) a link to
  `user/login/{social_network}` wherever on the site. This path is added by the
  implementers. For instance Social Auth Facebook will add the path
  `user/login/facebook`


RECOMMENDED MODULES
-------------------

* [Social Auth](https://www.drupal.org/project/social_auth):
  Implements methods and templates that will be used by login-related modules.

* [Social Post](https://www.drupal.org/project/social_post):
  Provides methods to allow auto posting to social network accounts.

* [Social Widgets](https://www.drupal.org/project/social_widgets):
  Allows sub-modules to add functionality to add widgets (like buttons, embedded
  content) to node, blocks, etc.


INSTALLATION
------------

Install as you would normally install a contributed Drupal module. See
[Installing Modules](https://www.drupal.org/docs/extending-drupal/installing-modules)
for details.


CONFIGURATION
-------------

* A table of implementers will be displayed at Administration » Configuration »
  Social API Settings » User authentication. However, it will be empty until an
  implementer has been installed and enabled.

* You should install implementer modules to get this module start working.

* You can place a Social Auth Login block at Administration » Structure »
  Block layout.


SUPPORT REQUESTS
----------------

Before posting a support request, carefully read the installation
instructions provided in module documentation page.

Before posting a support request, check Recent log entries at
admin/reports/dblog.

Once you have done this, you can post a support request at module issue queue:
[https://www.drupal.org/project/issues/social_auth](https://www.drupal.org/project/issues/social_auth)

When posting a support request, please inform what does the status report say
at admin/reports/dblog and if you were able to see any errors in
Recent log entries.


MAINTAINERS
-----------

Current maintainers:

 * [Christopher C. Wells (wells)](https://www.drupal.org/u/wells)

Development sponsored by:

 * [Cascade Public Media](https://www.drupal.org/cascade-public-media)
