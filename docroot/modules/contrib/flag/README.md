CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Support requests
 * History and Maintainers

INTRODUCTION
------------

The Flag module allows you to define a boolean toggle field and attach it to a
node, comment, user, or any entity type. You may define as many of these 'flags'
as your site requires. By default, flags are per-user. This means any user with
the proper permission may chose to flag the entity.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/flag

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/flag

REQUIREMENTS
------------

No special requirements.

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules
   for further information.

CONFIGURATION
-------------

Configuration of Flag module involves creating one or more flags.

 1. Go to Admin > Structure > Flags, and click "Add flag".

 2. Select the target entity type, and click "Continue".

 3. Enter the flag link text, link type, and any other options.

 4. Click "Save Flag".

 5. Under Admin > People, configure the permissions for each Flag.

Once you are finished creating flags, you may choose to use Views to leverage
your new flags.

SUPPORT REQUESTS
----------------

Before posting a support request, check Recent log entries at
admin/reports/dblog

Once you have done this, you can post a support request at module issue queue:
https://www.drupal.org/project/issues/flag

When posting a support request, please inform what does the status report say
at admin/reports/dblog and if you were able to see any errors in
Recent log entries.

HISTORY AND MAINTAINERS
-----------------------

This module was formerly known as Views Bookmark, which was originally was
written by Earl Miles. Later versions of Flag were written by Nathan Haug and
Mooffie. Flag 8.x was written by socketwench.

Current Maintainers:
 * Joachim Noreiko (joachim) - https://www.drupal.org/u/joachim
 * Shabana Navas (shabana.navas) - https://www.drupal.org/u/shabananavas
 * Tess (socketwench) - https://www.drupal.org/u/socketwench
 * Berdir - https://www.drupal.org/u/berdir
