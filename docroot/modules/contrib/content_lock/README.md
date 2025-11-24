# Content locking (anti-concurrent editing)

## Table of contents

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Forms
 * Recommendations
 * Maintainers

## INTRODUCTION

The Content Lock module prevents multiple users from trying to edit a content
entity simultaneously to prevent edit conflicts.

The purpose of this module is to avoid the situation where two people are
editing a single node at the same time. On busy sites with dynamic content,
edit collisions are a problem and may frustrate editors with an error stating
that the node was already modified and can't be updated. This module implements
a pessimistic locking strategy, which means that content will be exclusively
locked whenever a user starts editing it. The lock will be automatically
released when the user submits the form or navigates away from the edit page.

Content locks that have been "forgotten" can be automatically released after a
configurable time span using the bundled content_lock_timeout sub module.

 * For a full description of the module visit:
   https://www.drupal.org/project/content_lock

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/content_lock


## REQUIREMENTS

This module requires no modules outside of Drupal core.

## INSTALLATION

 * Install the Content Lock module as you would normally install a contributed
   Drupal module. Visit https://www.drupal.org/node/1897420 for further
   information.

## CONFIGURATION

  1. Navigate to Administration > Extend and enable the module.
  2. Navigate to Administration > People > Permissions to configure user
     permissions. The permissions are: "Administer Content Lock settings",
     which set on which entity type content lock is enabled and "Break content
     lock", which breaks a lock on content so that it may be edited.
  3. Navigate to Administration > Configuration > Content Authoring > Content
     Lock to administer content locking settings.
  4. Enable the "Verbose" option to display a message to the user when they
     lock a content item by editing it. Users trying to edit a content locked
     still see the content lock message.
  5. Select which entity type to have protections. Save configurations.

### Forms

To control what form operations content_lock will run on:

  1. Navigate to Administration > Configuration > Content Authoring > Content
     Lock (admin/config/content/content_lock).
  2. Under an enabled entity under "Lock only on entity form operation level."
     choose an option to enable for specific operations or disable specific operations.

## Recommendations

For extending or altering the string texts it is advised to use the
[String Overrides](https://www.drupal.org/project/stringoverrides) module.

## MAINTAINERS

 * Christian Fritsch (chr.fritsch) - https://www.drupal.org/u/chrfritsch
 * Daniel Bosen (daniel.bosen) - https://www.drupal.org/u/danielbosen
 * Christopher Gervais (ergonlogic) - https://www.drupal.org/u/ergonlogic
 * Joseph Zhao (pandaski) - https://www.drupal.org/u/pandaski
 * Volker Killesreiter (volkerk) - https://www.drupal.org/u/volkerk
 * Alex Pott (alexpott) - https://www.drupal.org/u/alexpott
 * Mark Burdett(mfb) - https://www.drupal.org/u/mfb

Supporting organizations:

 * Poetic Systems - https://www.drupal.org/poetic-systems
 * Thunder - https://www.drupal.org/thunder
