CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Credits
 * Maintainers


INTRODUCTION
------------

The FooTable module integrates the FooTable jQuery plugin into Drupal which
provides a responsive table plugin.

 * For a full description of the module, visit the project page:
   https://drupal.org/project/footable

 * To submit bug reports and feature suggestions, or to track changes:
   https://drupal.org/project/issues/search/footable


REQUIREMENTS
------------

If you're using the standalone version of the FooTable plugin, which can be
configured via Configuration » User interface » FooTable » Settings, you need
to include the Font Awesome (https://fortawesome.github.io/Font-Awesome) font
in your project. The easiest way to do this is by installing the following
module:

 * [Font Awesome Icons](https://www.drupal.org/project/fontawesome)


INSTALLATION
------------

 * Module: Install as you would normally install a contributed Drupal module.
   See: https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules
   for further information.

 * Plugin: Download the most recent 3.x.x version of the FooTable jQuery plugin
   from https://github.com/fooplugins/FooTable/tags and copy the compiled
   directory to the libraries/footable directory.

   Installation using Composer:

   If you are installing this module using Composer, you can also download the
   library by adding for example the following lines into your composer.json file:

     "repositories": {
       ...
       "footable": {
         "type": "package",
         "package": {
           "name": "fooplugins/footable",
           "version": "3.1.6",
           "type": "drupal-library",
           "dist": {
             "url": "https://github.com/fooplugins/FooTable/archive/3.1.6.zip",
             "type": "zip"
           }
         }
       },
       ...
     }
     "require": {
       ...
       "fooplugins/footable": "3.*",
       ...
     }


CONFIGURATION
-------------

 * Configure user permissions in Administration » People » Permissions:

   - Administer FooTable

     Users in roles with the "Administer FooTable" permission will be able to
     change the configuration of the FooTable module via Configuration »
     User interface » FooTable.

 * Customize the module in Configuration » User interface » FooTable.

   - Breakpoints
   - Settings

 * Enable the FooTable module for a view using the following steps:

   - When creating a view, select the *FooTable* format.
   - Click on the *Settings* link, under the *Format* section.
   - Scroll down to the *FooTable Settings* section.
   - Configure the general FooTable settings accordingly.
   - Under the *Collapsed Columns* section, choose the "breakpoints" for which
     each column should be hidden.


CREDITS
-------

This module would not be possible without the FooTable plugin itself. Thanks to
[Bradvin & Steveush](https://github.com/orgs/fooplugins/people) for making it
available and to all the others who helped inspire it.

Thanks to [Matt Vance](https://drupal.org/user/88338) for all his on the
7.x-1.x branch and the initial setup of this project.

This module is largely based on code from the
[DataTables](http://drupal.org/project/datatables) module. Many thanks to
[Duellj](http://drupal.org/user/168159) for developing and contributing that
module.


MAINTAINERS
-----------

Current maintainers:
 * [Mitch Portier (Arkener)](https://drupal.org/user/2284182)
