<?php

namespace Drupal\layout_builder_st\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'layout_section' field type.
 *
 * @internal
 *
 * @FieldType(
 *   id = "layout_translation",
 *   label = @Translation("Layout Translation"),
 *   description = @Translation("Layout Translation"),
 *   no_ui = TRUE,
 *   cardinality = 1,
 *   list_class = "\Drupal\layout_builder_st\Field\LayoutTranslationItemList",
 * )
 */
final class LayoutTranslationItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('layout_translation')
      ->setLabel(new TranslatableMarkup('Layout Translation'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'value' => [
          'type' => 'blob',
          'size' => 'normal',
          'serialize' => TRUE,
        ],
      ],
    ];

    return $schema;
  }

}
