# Menu Item Extras

Menu Item Extras provides extra fields for the Menu Items without
using additional entities. This module is NOT an alternative of all
Mega Menu modules - it is unique implementation of Drupal core’s
Menu system.
All fields are placed upon the Menu Item edit/add form and use core
Menu link content entity for storing all data.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/menu_item_extras).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/menu_item_extras).


## Table of contents

- Requirements
- Installation
- Configuration
- Uninstalling
- FAQ
- Maintainers


## Requirements

This module requires the following modules:

- Block (Drupal core)
- Text (Drupal core)
- Menu link content (Drupal core)


## Installation

1. Download and enable as normal module;
2. Go to the menus list and edit menu which you want to have the extras.


## Configuration

1. You can enable/disable extras per menu, by default we enable extras for
   the Main Menu.
   When extras is disabled, all data from the fields are removed;
2. We added more suggestions for menus in regions. You could change menu
   template per region. Like `menu--extras--main--header.html.twig`,
  `menu--extras--main--footer.html.twig`.


## Uninstalling

1. Disable extras for all menus otherwise, you will not be able to uninstall it;
2. Uninstall as a normal module.


## FAQ

1. How to get a field value in the template?

 You can use entity parameter which is passed to the `menu-levels.html.twig`
```
    {% if item.entity.field_test.value == '1' %}
      {{ rendered_content }}
    {% endif %}
```

2. Support for REST API
We do not support it out of the box, check the issue
`https://www.drupal.org/project/menu_item_extras/issues/2959787`


## Maintainers

- Oleh Vehera - [voleger](https://www.drupal.org/u/voleger)
- Andriy Khomych - [Andriy Khomych](https://www.drupal.org/u/andriy-khomych)
- Mykhailo Gurei - [ozin](https://www.drupal.org/u/ozin)
- Vladimir Roudakov - [VladimirAus](https://www.drupal.org/u/vladimiraus)
