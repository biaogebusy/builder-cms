# Example Template

The Paragraphs Tabs Widget module provides an alternative widget for paragraphs: it displays each paragraph entity's widget in a set of tabs.

Currently, only a vertical tabs widget is provided, but contributions to add accessible alternate tab widgets would be welcome.


## Requirements

This module requires the following modules:

- [Paragraphs](https://www.drupal.org/project/paragraphs)


## Installation

Install as you would normally install a contributed Drupal module. For further information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

1. Configure the user permissions in Administration » People » Permissions:
    - Change summary selector.

        The widget settings (in 'Manage form display') contains a
        'Tab summary selector' setting, whose value will be evaluated by jQuery in
        the client's browser. Warning: Give to trusted roles only; this permission
        has security implications.

2. Customize your field's form display settings to use the "Vertical tabs"
    widget. For paragraph reference fields on nodes, you would do this from
    Administration » Structure » Content types » (your content type)
    » Manage form display.


## Troubleshooting

* If you cannot see the vertical tab widget, but you are certain that it is
    selected at Administration » Structure » Content types » (your content type)
    » Manage form display (the Widget should be "Vertical tabs"), then it is
    likely that your theme is interfering with the widget.

    Unfortunately, Drupal core's "vertical_tabs" FormElement is fragile: the
    HTML details elements (i.e.: the tab contents) must be child elements (i.e.:
    not descendant elements) of the HTML element with the
    `data-vertical-tabs-panes` attribute. See Drupal core's
    `core/misc/vertical-tabs.es6.js` or `core/misc/vertical-tabs.js` for more 
    information.
