# Select (or other)

Provides a new Forms API element which is a select/radios/checkboxes element
that has an 'other' option. When 'other' is selected a textfield appears for
the user to provide a custom value.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/select_or_other).

To submit bug reports and feature suggestions, or to track changes
[issue queue](https://www.drupal.org/project/issues/select_or_other).


## Contents of this file

- Requirements
- Installation
- Applications
- Usage example
    - Field widget
    - Forms API element
- Maintainers


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. Visit
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules)
for further information.


## Applications

- As a Field widget for (auto create) Entity reference fields.
- As a Forms API element for developers. Therefor can be integrated into any
  form or module.


## Usage examples

Reference widget: Uses a taxonomy vocabulary to populate the options list.
When new terms are added via the "other" choice, they are added to the
options list.

List widget: Similar to Drupal 7, adds an "other" option to a select list,
such as "List (text)." Whether new terms are added via the "other"
choice is configurable.

#### Reference widget

First, create a taxonomy vocabulary to contain the options (terms) that will
populate the select list. Then:

1. Open your content type or other entity type and go to the manage fields
   tab, or create a new entity type.
2. Add a field with field type reference/taxonomy term.
3. Edit that field and select "Create referenced entities if they don't
   already exist," then save.
4. Go to Manage form display and select "Select or Other" in the
   Widget column.

When adding new content, selecting the 'other" option will open a text box
where a new term/option can be entered. New terms will be added to the
options/terms list when a user chooses the "other" option and submits the
entity.

#### List widget

1. Open your content type or other entity type and go to the manage fields
   tab, or create a new entity type.
2. Add a field with a list field type, such as "List (text)" or
   "List (integer)." Enter the allowed values for that field (but not "other").
3. On the "manage form display" tab, choose "select or other." In the
   settings (gear icon) for that field, toggle the checkbox "Add entered
   values from the other field to the allowed values list" to configure
   whether options added via "other" will be added to the default options list.

When adding new content, selecting the 'other" option will open a text box
where a new term/option can be entered. New terms will be added to the
list if "Add entered values from the other field to the allowed values
list" is checked.

### Forms API element

For the developers, this example is about the Forms API element. Start with
the custom module tutorial at:
[Add a Form to the Block Configuration](https://www.drupal.org/docs/8/creating-custom-modules/add-a-form-to-the-block-configuration)

Then go to `/hello_world/src/Plugin/Block/HelloBlock.php` and place the
following before `return $form;`:

```
    $form['hello_block_settings'] = array(
        '#type' => 'select_or_other_select',
        '#title' => t('Options'),
        '#options' => array(
            'value_1' => t('One'),
            'value_2' => t('Two'),
        ),
        '#multiple' => TRUE,
        '#other_unknown_defaults' => 'other',
        '#other_delimiter' => ', ',
    );
```

The menu should now appear in the block settings. For more information visit
[Select (or other)](https://www.drupal.org/node/1158654).


## Maintainers

- Chris Jansen [legolasbo](https://www.drupal.org/u/legolasbo)
- Henrique Mendes [hmendes](https://www.drupal.org/u/hmendes)
- [danielb](https://www.drupal.org/u/danielb)
- [Jon Heaton](https://www.drupal.org/u/haydeniv)
- [Dieter Blomme](https://www.drupal.org/u/daften)
- [Vlad Bondarchuk](https://www.drupal.org/u/vladbo)
- [Anton Kuzmenko](https://www.drupal.org/u/qzmenko)
