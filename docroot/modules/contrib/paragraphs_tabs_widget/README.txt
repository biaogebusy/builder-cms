CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Troubleshooting
 * Maintainers

INTRODUCTION
------------

The Paragraphs Tabs Widget module provides an alternative widget for paragraphs:
it displays each paragraph entity's widget in a set of tabs. Currently, only a
vertical tabs widget is provided, but contributions to add accessible alternate
tab widgets would be welcome.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/paragraphs_tabs_widget

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/paragraphs_tabs_widget

REQUIREMENTS
------------

This module requires the following modules:

 * Paragraphs (https://www.drupal.org/project/paragraphs)

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.

CONFIGURATION
-------------

 * Configure the user permissions in Administration » People » Permissions:

   - Change summary selector.

     The widget settings (in 'Manage form display') contains a
     'Tab summary selector' setting, whose value will be evaluated by jQuery in
     the client's browser. Warning: Give to trusted roles only; this permission
     has security implications.

 * Customize your field's form display settings to use the "Vertical tabs"
   widget. For paragraph reference fields on nodes, you would do this from
   Administration » Structure » Content types » (your content type)
   » Manage form display.

TROUBLESHOOTING
---------------

 * If you cannot see the vertical tab widget, but you are certain that it is
   selected at Administration » Structure » Content types » (your content type)
   » Manage form display (the Widget should be "Vertical tabs"), then it is
   likely that your theme is interfering with the widget.

   Unfortunately, Drupal core's "vertical_tabs" FormElement is fragile: the HTML
   details elements (i.e.: the tab contents) must be child elements (i.e.: not
   descendant elements) of the HTML element with the data-vertical-tabs-panes
   attribute. See Drupal core's core/misc/vertical-tabs.es6.js or
   core/misc/vertical-tabs.js for more information.

MAINTAINERS
-----------

Current maintainers:
 * Christopher Gervais (ergonlogic) - https://www.drupal.org/user/368613
 * Colan Schwartz (colan) - https://www.drupal.org/user/58704
 * Dan Friedman (llamech) - https://www.drupal.org/user/3607172
 * Derek Laventure (spiderman) - https://www.drupal.org/user/1631
 * Joseph Leon (josephleon) - https://www.drupal.org/user/1891828
 * M Parker (mparker17) - https://www.drupal.org/user/536298
 * Seonaid Lee (Seonaid) - https://www.drupal.org/user/3642013
 * szhu - https://www.drupal.org/user/3525295

This project has been sponsored by:
 * CONSENSUS ENTERPRISES
   We help small teams do big things.

 * HEALTH CANADA - HEALTH PRODUCTS AND FOOD BRANCH
   Health Canada is responsible for helping Canadians maintain and improve their
   health. It ensures that high-quality health services are accessible, and
   works to reduce health risks.
