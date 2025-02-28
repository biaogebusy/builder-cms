
# CONTENTS OF THIS FILE

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * FAQ
 * Maintainers

## INTRODUCTION

Menu Item Extras provides extras for the Menu Items.
Version 8.x-1.x adds the Body textarea field only.

## REQUIREMENTS

This module requires the following modules:

 * Block (Drupal core)
 * Text (Drupal core)
 * Menu link content (Drupal core)

## INSTALLATION:

1. Download and enable as normal module;
2. Go to the menus list and edit menu which you want to have the extras.

## CONFIGURATION

* You can enable/disable extras per menu, by default we enable extras for
  the Main Menu.
  When extras is disabled, all data from the fields are removed;
* We added more suggestions for menus in regions. You could change menu
  template per region. Like `menu--extras--main--header.html.twig`,
  `menu--extras--main--footer.html.twig`.

## UNINSTALLING:

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
https://www.drupal.org/project/menu_item_extras/issues/2959787
## MAINTAINERS

- Andriy Khomych(andriy.khomych) https://www.drupal.org/u/andriy-khomych
- Bogdan Hepting() https://www.drupal.org/u/bogdan-hepting
- Oleh Vehera(voleger) https://www.drupal.org/u/voleger
- Mykhailo Gurei(ozin) https://www.drupal.org/u/ozin
