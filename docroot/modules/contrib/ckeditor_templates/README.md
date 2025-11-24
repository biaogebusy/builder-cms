# CKEDITOR TEMPLATES

## CONTENTS OF THIS FILE


- Introduction
- Audience
- Requirements
- Installation
- Configuration
- Warning
- Migrating from CKeditor 4
- Maintainers

## INTRODUCTION


This module provides templates for CKEditor 5.

It provides a dialog to offer predefined content templates - with page layout,
text formatting, and styles. Thus, end users can easily insert pre-defined
snippets of html in CKEditor fields.

- For a full description of the module, visit the project page:
  https://www.drupal.org/project/ckeditor_templates

- To submit bug reports and feature suggestions, or to track changes:
  https://www.drupal.org/project/issues/ckeditor_templates?categories=All

## AUDIENCE


This module is intended for themers who can manage custom ckeditor templates
from their theme. As is, it doesn't provide any functionality.

## REQUIREMENTS


This module requires CKEditor 5.

## INSTALLATION


Install this Drupal module as you would normally install a contributed
Drupal module. For more information, visit:
https://www.drupal.org/documentation/install/modules-themes/modules-8


## CONFIGURATION

- First, add the plugin button in your editor toolbar: go to the format and
editor config page (/admin/config/content/formats), and click configure on the
format your want to edit.
- Next, create the templates: go to the CKEditor Templates config page
(/admin/config/content/ckeditor-templates) and add as many templates as needed.


## WARNING


Depending on the configuration of your formats, CKEditor can be restrictive
about authorized HTML tags. Therefore, make sure to use compatible HTML tags
in your templates.


## MIGRATING FROM CKEDITOR 4
 

If you had this module installed for CKEditor 4, all templates will not be
migrated to the new version for CKEditor 5. Instead, you will have to manually
create the templates in the new version, as described above.



## MAINTAINERS


Current maintainers:
- Lucas Le Goff - [lucaslg](https://www.drupal.org/user/3128975)

This project has been sponsored by:
- Micropole
  Visit https://www.micropole.com for more information.
