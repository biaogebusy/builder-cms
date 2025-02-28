CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

The Private Message module allows for private messages between users on a site.
It has been written to be fully extendable using Drupal 8 APIs.

 * For a full description of the module visit
   https://www.drupal.org/node/2871948

 * To submit bug reports and feature suggestions, or to track changes visit
   https://www.drupal.org/project/issues/private_message


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

Install the Private Message module as you would normally install a contributed
Drupal module. Visit https://www.drupal.org/node/1897420 for further
information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the Private Message
       module.
    2. Navigate to Administration > People > Permissions and give the two
       permissions (use private messaging system, access user profiles)
       to the roles that should use the messaging system. Save permissions.
    3. To write a private message to another user, navigate to the path
       /private-messages.

Configuring the Private Message Inbox Block
    1. Navigate to Administration > Structure > Block Layout.
    2. Find the Private Message Inbox Block and select the Configure button.
    3. Give the block a title.
    4. Select the number of threads to show in the block.
    5. Select the number of threads to be loaded with ajax.
    6. Select an Ajax refresh rate. This is the number of seconds between checks
       if there are any new messages. Note: setting this number to zero will
       disable refresh and the inbox will only be refreshed upon page refresh.
    7. In the Visibility horizontal tab section there are three options for
       visibility: Content types, Pages and Roles.
    8. The user may also want to set the block to only show on the following
       paths:
        * /private-messages
        * /private-messages/*
       This will limit the block to only show on private message thread pages.
    9. Select to region for block display from the Region dropdown.
    10. Save block.

Configuring the Private Message Notification Block
    1. Navigate to Administration > Structure > Block Layout.
    2. Find the Private Message Notification Block and select the Configure
       button.
    3. Give the block a title.
    4. Select an Ajax refresh rate. This is the number of seconds between checks
       if there are any new messages. Note: setting this number to zero will
       disable refresh and the inbox will only be refreshed upon page refresh.
    5. In the Visibility horizontal tab section there are three options for
       visibility: Content types, Pages and Roles.
    6. Select to region for block display from the Region dropdown.
    7. Save block.

To Configure Private Message Threads
    1. Navigate to Administration > Structure > Privates Messages > Private
       Message Threads.
    2. Select the Manage fields tab and fields can be added as with any other
       entity.
    3. Select the Manage display to order the items in a thread.
    4. Save block.

For other use stories and configurations, please visit
https://www.drupal.org/node/2871948


Note: if Bartik is not the enabled theme, the Private Message Inbox block will
need to be placed in a region somewhere on the page.


MAINTAINERS
-----------

Current maintainers:

 * Jay Friendly (Jaypan) - https://www.drupal.org/u/jaypan
 * Eduardo Telaya (edutrul) - https://www.drupal.org/u/edutrul

Supporting organizations:

 * Jaypan - https://www.drupal.org/jaypan
