# Field Permissions

The Field Permissions module allows site administrators to set field-level
permissions for fields that are attached to any kind of entity (such as nodes
or users).

Permissions can be set for editing or viewing the field (either in all
contexts, or only when it is attached to an entity owned by the current user).
Permissions can also be set for editing the field while creating a new entity.

Permissions for each field are not created by default. Instead, administrators
can enable these permissions explicitly for the fields where this feature is
needed.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/field_permissions).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/field_permissions).


## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers


# Requirements

This module requires the following modules:

- [Field](https://www.drupal.org/project/field)


## Installation

- Copy all contents of this package to your modules directory preserving
   subdirectory structure.

- Go to Administer -> Modules to install module. If the (Drupal core) Field UI
   module is not enabled, do so.

- Review the settings of your fields. You will find a new option labelled
   "Field visibility and permissions" that allows you to control access to the
   field.

- If you chose the setting labelled "Custom permissions", you will be able to
   set this field's permissions for any role on your site directly from the
   field edit form, or later on by going to the Administer -> People ->
   Permissions page.

- Get an overview of the Field Permissions at:
   Administer -> Reports -> Field list -> Permissions


## Configuration

Once Field Permissions module is installed, you need to edit the field settings
form to enable permissions for each field where you need this feature. You can
choose from three options:

- Not set (Field inherits content permissions.)
- Private (only author and administrators can edit and view)
- Custom permissions

The default value ("Public") does not impose any field-level access control,
meaning that permissions are inherited from the entity view or edit
permissions. For example, users who are allowed to view a particular node that
the field is attached to will also be able to view the field.

"Private" provides quick and easy access to a commonly used form of field
access control.

Finally, if "Custom permissions" is chosen, a standard permissions matrix will
be revealed allowing you full flexibility to assign the following permissions
to any role on your site:

- Create own value for field FIELD
- Edit own value for field FIELD
- Edit anyone's value for field FIELD
- View own value for field FIELD
- View anyone's value for field FIELD

These permissions will also be available on the standard permissions page at
Administer -> People -> Permissions.


# Maintainers

- Jakob Perry - [japerry](https://www.drupal.org/u/japerry)
- Jonathan Hedstrom - [jhedstrom](https://www.drupal.org/u/jhedstrom)
- Marc Ferran - [markus_petrux](https://www.drupal.org/u/markus_petrux)
- Rob Loach - [Rob Loach](https://www.drupal.org/u/robloach)
- Maria Fisher - [mariacha1](https://www.drupal.org/u/mariacha1)
