CONTENTS OF THIS FILE
---------------------
 * Introduction
 * Requirements
 * Recommended Modules
 * Installation
 * Configuration

INTRODUCTION
------------
After a given timeout has passed, users are given a configurable session
expiration prompt. They can reset the timeout, logout, or ignore it, in which
case they'll be logged out after the padding time is elapsed. This is all backed
up by a server side logout if JS is disabled or bypassed.

REQUIREMENTS
------------
None.

RECOMMENDED MODULES
-------------------
 * Session Limit (https://www.drupal.org/project/session_limit)
 * Password Policy (https://www.drupal.org/project/password_policy)

INSTALLATION
------------
 * Install as usual:
 See https://www.drupal.org/documentation/install/modules-themes/modules-8
 for further information.

CONFIGURATION
-------------
* Configure permissions : Home >> Administration >> People
  (/admin/people/permissions)
* Configure Automated logout : Home >> Administration >> Configuration >> People
  (/admin/config/people/autologout)
* Configurable "Global timeout" and "Timeout padding".
  The latter determines how much time a user has to respond to the prompt
  and when the server side timeout will occur.
* Configurable messaging.
* Configurable "Redirect URL" with the destination automatically appended.
* Configure which roles will be automatically logged out.
* Configure if a logout will occur on admin pages.
* Integration with ui.dialog if available.
  This makes for attractive and more functional dialogs.
* Configurable timeout based on user.
* Configurable maximum timeout.
  Primarily used when a user has permission to change their timeout value,
  this will be a cap or maximum value they can use.
* Order of precedence is : user timeout -> lowest role timeout -> global
  timeout.
* So if a user has a user timeout set, that is their timeout threshold,
  if none is set the lowest timeout value based on all the roles the user
  belongs to is used, if none is set the global timeout is used.
* Make sure the timeout value in seconds is smaller than the value for
  session.gc_maxlifetime. Otherwise your session will be destroyed before
  autologout kicks in.
