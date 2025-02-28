# REST Views

This module enhances the functionality of Views that use the REST Export
display to export serialized data.

## Introduction

When you create a view with a REST Export display, you will quickly encounter
hard limitations on the data structure, imposed by the Views module's reliance
on the Render API:

 - You may want to export a field with multiple values as an array.
   By default, this is only possible by exporting the raw field, as the
   rendering process also concatenates the output into a single string.
 - An entity reference can only be exported as a fully rendered string,
   and not as a nested structure with multiple individual fields.
 - Boolean and numeric fields can only be exported as strings, rather than
   the appropriate JSON primitives.

The Views and Field plugins in this module add all of these features.

## Usage

After installing this module, each entity field can be accessed in Views
via a new handler named "*Field (Serializable)*".

This field handler automatically exports multiple-value fields as arrays.
This is dependent on the cardinality in the schema, so it will be applied
even if the field contains less than two actual values.

Furthermore, the following fields will have new field formatters designed
to export non-string values:

 - Boolean
 - Numeric
 - Entity Reference
 - [Entity Reference Revisions](https://drupal.org/project/entity_reference_revisions)
 - Image

(Note that these will only work in combination with the *Serializable* handler.)

## Extending

You can create your own field formatter plugins that export arbitrary
data structures based on a field's values:

```php
<?php

namespace Drupal\my_module\Plugins\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\rest_views\SerializedData;

/**
 * A sample REST-aware field formatter for a custom field.
 *
 * @FieldFormatter(
 *   id = "my_field_export",
 *   label = @Translation("Export my field"),
 *   field_types = {
 *     "my_field",
 *   }
 * )
 */
class MyFieldExportFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $data = '[A data structure based on $item.]';
      $elements[$delta] = [
        '#type' => 'data',
        '#data' => SerializedData::create($data),
      ];
    }

    return $elements;
  }

}
```

## Security

This module does not add any output filtering beyond that which has already
been applied. In practice this means:

- All fields with normal non-*Export* formatters (including those
  inside referenced entities) are rendered, and therefore contain
  safe HTML markup, provided their modules are working securely.
- The *Export* field formatters export raw values.

If both kinds of formatters are used in the same view, clients
should be made aware which values are markup and which are
untreated strings. In all cases, the client application is responsible
for safely using the exported data.
