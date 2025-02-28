CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

The Multiselect module defines a widget to be used with CCK fields. It allows
the user to select multiple items in an easy way. It consists of two lists, one
of all available items, the other of selected items. The user can select an item
by moving it from the unselected list to the selected list.

 * For a full description of the module visit:
   https://www.drupal.org/project/multiselect

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/multiselect


REQUIREMENTS
------------

This Multiselect module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install the Multiselect module as you would normally install a contributed
   Drupal module. Visit https://www.drupal.org/node/1897420 for further
   information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module.
    2. Navigate to Administration > Configuration > Content authoring >
       Multiselect to configure how multiselect fields are displayed to content
       editors.
    3. Select the width of Select Boxes (in pixels). Save configuration.
    4. Navigate to Content types > Content type to add field to > Manage fields
    5. Add new field of type List, List (Text), List (Number), Node reference,
       Taxonomy term reference, and User reference.
    6. Navigate to Manage form display > Select Multiselect as the field
       widget. Save configuration.

Methods of Implementing a Multiselect Widget:

Method 1: Using CCK
When creating a new content field, select "Multiselect" as your widget type. You
can use Multiselect on fields of type "list", "list_text", "list_number",
"node_reference", "taxonomy_term_reference", and "user_reference".

Method 2: Coding Your Own Module
If you're developing a custom module and wish to use the Multiselect widget in
place of a traditional "select" widget, you may use the Drupal 8 Form API.


MAINTAINERS
-----------

 * Adam Bergstein (nerdstein) - https://www.drupal.org/u/nerdstein
 * Mark W. Jarrell (attheshow) - https://www.drupal.org/u/attheshow
 * Alex Weber (alexweber) - https://www.drupal.org/u/alexweber

Supporting organizations:

 * Drupal 8 porting support: CivicActions - https://www.drupal.org/civicactions
 * Drupal 6, 7, & 8 support (attheshow): FleetThought -
   https://www.drupal.org/fleetthought
 * Module maintenance: Hook 42 - https://www.drupal.org/hook-42
