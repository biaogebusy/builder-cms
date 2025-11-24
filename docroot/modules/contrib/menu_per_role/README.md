# Menu Per Role

This module allows you to restrict access of menu items per roles.

**Limitation:**

This module only acts on content menu link (content entity). Menu links
provided by configuration (example: Views) or by `*.links.menu.yml` files can't
be managed by this module.


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

Once enabled, go to the global settings (`/admin/config/system/menu_per_role`)
page to configure the module.

Edit a menu item as usual. There will be one or two fieldsets, depending on the
configuration of the module, that allows you to restrict access by role.

If you don't check any roles the default access permissions will be kept.
Otherwise, the module will additionally restrict access to the chosen user
roles.
