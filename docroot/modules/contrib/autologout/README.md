# AUTO LOGOUT

After a given timeout has passed, users are given a configurable session
expiration prompt. They can reset the timeout, logout, or ignore it, in which
case they'll be logged out after the padding time is elapsed. This is all backed
up by a server side logout if JS is disabled or bypassed.

## Table of Contents

- Introduction
- Requirements
- Recommended Modules
- Installation
- Configuration

## Requirements

- drupal/js_cookie --> introduced when core js was split into a separate module.

## Recommended Modules

- Session Limit (https://www.drupal.org/project/session_limit)
- Password Policy (https://www.drupal.org/project/password_policy)

## Installation

 * Install as usual:
 See https://www.drupal.org/documentation/install/modules-themes/modules-8
 for further information.

## Configuration

1. Configure permissions : Home >> Administration >> People
  (/admin/people/permissions)
1. Configure Automated logout : Home >> Administration >> Configuration >> People
  (/admin/config/people/autologout)
1. Configurable "Global timeout" and "Timeout padding".
  The latter determines how much time a user has to respond to the prompt
  and when the server side timeout will occur.
1. Configurable messaging.
1. Configurable "Redirect URL" with the destination automatically appended.
1. Configure which roles will be automatically logged out.
1. Configure if a logout will occur on admin pages.
1. Integration with ui.dialog if available.
  This makes for attractive and more functional dialogs.
1. Configurable timeout based on user.
1. Configurable maximum timeout.
  Primarily used when a user has permission to change their timeout value,
  this will be a cap or maximum value they can use.
1. Order of precedence is : user timeout -> lowest role timeout -> global
  timeout.
1. So if a user has a user timeout set, that is their timeout threshold,
  if none is set the lowest timeout value based on all the roles the user
  belongs to is used, if none is set the global timeout is used.
1. Make sure the timeout value in seconds is smaller than the value for
  session.gc_maxlifetime. Otherwise your session will be destroyed before
  autologout kicks in.
