CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Recommended Modules
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

The Quick Node Clone module adds a "Clone" tab to a node. When selected, a new
node is created and fields from the previous node are populated into the new
fields.

This is potentially duplicate work of Node Clone
(https://www.drupal.org/project/node_clone), but as of release they don't have
a stable D8 version and this code was created for a project from scratch in a
reusable manner. This is focused on supporting more variety in field types than
core's default cloning.

Future @TODO: Support more than just nodes! It could be expanded to all Content
Entities fairly easily. This will likely be in its own properly named module
with a better method for adding a UI to other content entities.

 * For a full description of the module visit:
   https://www.drupal.org/project/quick_node_clone

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/quick_node_clone


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


RECOMMENDED MODULES
-------------------

It currently supports cloning of most field types including Inline Entity Form
and Field Collection.

 * Inline Entity Form - https://www.drupal.org/project/inline_entity_form
 * Field collection - https://www.drupal.org/project/field_collection


INSTALLATION
------------

Install the Quick Node Clone module as you would normally install a contributed
Drupal module. Visit https://www.drupal.org/node/1897420 for further
information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the Quick Node Clone
       module.
    2. A Clone tab is now available on nodes.
    3. Select the Clone tab and a new node is created and the fields from the
       previous node are populated.
    4. Make appropriate edits and Save New Clone.


MAINTAINERS
-----------

 * David Lohmeyer (vilepickle) - https://www.drupal.org/u/vilepickle
 * Neslee Canil Pinto - https://www.drupal.org/u/neslee-canil-pinto
