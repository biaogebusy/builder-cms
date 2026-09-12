# Automated Logout

## Introduction

After a given timeout has passed, users are given a configurable session
expiration prompt. They can reset the timeout, logout, or ignore it, in which
case they'll be logged out after the padding time is elapsed. This is all backed
up by a server side logout if JS is disabled or bypassed.

Submit bug reports or feature suggestions in the
[Autologout issue queue](https://www.drupal.org/project/issues/autologout).


## Recommended Modules

- [Session Limit](https://www.drupal.org/project/session_limit)
- [Password Policy](https://www.drupal.org/project/password_policy)


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-modules).


## Configuration

- Configure permissions: Home >> Administration >> People
  `/admin/people/permissions`
- Configure Automated logout: Home >> Administration >> Configuration >> People
  `/admin/config/people/autologout`
- Configurable **Global timeout** and **Timeout padding**.
  The latter determines how much time a user has to respond to the prompt
  and when the server side timeout will occur.
- Configurable messaging, with support for HTML markup in logout messages.
- Configurable **Redirect URL** with the destination automatically appended.
- Configure which roles will be automatically logged out.
- Configure if a logout will occur on admin pages.
- Uses `Drupal.dialog` for session expiration prompts, providing accessible
  and modern dialogs compatible with Drupal 10 and 11.
- Configurable timeout based on user.
- Configurable maximum timeout.
  Primarily used when a user has permission to change their timeout value,
  this will be a maximum value they can use.
  Order of precedence is: user timeout -> the lowest role timeout -> global
  timeout.
  If a user has a user timeout set, that is their timeout threshold,
  if none is set the lowest timeout value based on all the roles the user
  belongs to is used, if none is set the global timeout is used.
- JavaScript is only loaded for authenticated users, reducing page weight
  for anonymous visitors.
- The autologout cookie is set as secure.
- Logout is synchronised across multiple browser tabs — logging out in one
  tab will log the user out of all other open tabs.
- Compatible with CKEditor5, keeping sessions alive during active editing.
- Make sure the timeout value in seconds is smaller than the value for
  session.gc_maxlifetime. Otherwise, your session will be destroyed before
  autologout kicks in.


## Developer API

Three hooks are available for customising autologout behaviour:

- `hook_autologout_prevent()` — return `TRUE` to prevent autologout on
  specific pages (e.g. AJAX callbacks).
- `hook_autologout_refresh_only()` — return `TRUE` to keep the session
  alive on specific pages without triggering a timeout (e.g. long-form editing).
- `hook_autologout_user_logout()` — fires after a user has been logged out
  via autologout (not on manual logout).

See `autologout.api.php` for full documentation.


## FAQ

**Q: My site's `services.yml` does not include `cookie_samesite` under `session.storage.options`. Is this a problem?**

**A:** Yes — `cookie_samesite` controls the SameSite attribute on session cookies and is
an important security measure against CSRF attacks. It was added to Drupal core's
`default.services.yml` in Drupal 10.1 ([#3150614](https://www.drupal.org/project/drupal/issues/3150614)).
Sites set up before that release may have a `services.yml` that predates this change.
Because `session.storage.options` is replaced rather than merged when overridden, any
keys absent from your `services.yml` will not inherit core's defaults. It is worth
comparing your `sites/default/services.yml` against the current `default.services.yml`
shipped with your version of Drupal core to ensure no other options are missing.
At a minimum, `cookie_samesite` should be set:

    session.storage.options:
      cookie_samesite: Lax

See the [Drupal services.yml documentation](https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection/structure-of-a-service-file)
for more information.


**Q: How to upgrade between versions?**

**A:** After updating the module, run database updates using **drush updb**,
**drush updatedb**, or browser **/update.php**.
Once database updates complete, a configuration export is required to save
any newly added fields and settings.
Configuration export can be done using **drush cex** or in the configuration
UI `admin/config/development/configuration` where the **Export** tab is used
for full or single file export.


## Maintainers

- Ajit Shinde - [(AjitS)](https://www.drupal.org/u/ajits)
- Bostjan Kovac - [(boshtian)](https://www.drupal.org/u/boshtian)
- John Ennew - [(johnennew)](https://www.drupal.org/u/johnennew)
- James Glasgow - [(jrglasgow)](https://www.drupal.org/u/jrglasgow)
- Gareth Alexander - [(the_g_bomb)](https://www.drupal.org/u/the_g_bomb)
- Jakob Perry - [(japerry)](https://www.drupal.org/u/japerry)
- Andy Kirkham - [(AjK)](https://www.drupal.org/u/ajk)
- Dan Andrews - [(dandrews)](https://www.drupal.org/u/dandrews)
- Lev Tsypin - [(levelos)](https://www.drupal.org/u/levelos)
- Prabin Giri - [(prabeen.giri)](https://www.drupal.org/u/prabeengiri)
- K M - [(kmasood)](https://www.drupal.org/u/kmasood)
- Martin Fraser - [(darksnow)](https://www.drupal.org/u/darksnow)
- Alexander Shudra - [(str8)](https://www.drupal.org/u/str8)
