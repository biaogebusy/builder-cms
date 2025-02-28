CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

The Recently Read module displays the history of recently read Entity a
particular user has viewed. Each authenticated user has its own history
recorded, so this module may be useful i.e. for displaying recently viewed
products on the e-commerce site. The history is displayed as a block and each
content type gets its own block.

 * For a full description of the module visit:
   https://www.drupal.org/project/recently_read

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/recently_read


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install the Recently Read module as you would normally install a contributed
   Drupal module. Visit https://www.drupal.org/node/1897420 for further
   information.


CONFIGURATION
-------------

  1. Navigate to Administration > Extend and enable the module.
  2. When the module is enabled, it automatically creates a view block for
       recently read articles.
  3. Navigate to Administration > Configuration > System > Recently read config to
       configure.
  4. From the "Configuration" tab, enable entity types for which you want
       to track recently read items. There are also options for deleting the records:
       Time based, Count based, or Never.
  5. From the "List" tab, entities can be individually configured by
       selecting the "Edit" option.
  6. Navigate to Administration > Structure > Views to create a new view for
       Recently read content (Content) just add a relationship to Recently read
       with Require this relationship option checked. See (Recently read content)
       as an example.


MAINTAINERS
-----------

First versions of Recently read module were written by Przemyslaw Gorecki and
Terry Zhang. Recently read 8.x was written by Nejc Koporec and Janez Zibelnik.

Maintainer for 8.x:
 * Nejc Koporec(nkoporec) - https://www.drupal.org/u/nkoporec
 * Janez Zibelnik(janezzibelnik) - https://www.drupal.org/u/janezzibelnik

Maintainer for 7.x
 * Przemyslaw Gorecki (pgorecki) - http://drupal.org/user/642012
 * Terry Zhang (zterry95) - http://drupal.org/user/1952394
