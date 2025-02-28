<?php

namespace Drupal\core_context\Plugin\Field\FieldType;

use Drupal\Component\Plugin\Context\Context;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'context' field type.
 *
 * @FieldType(
 *   id = "context",
 *   label = @Translation("Context"),
 *   description = @Translation("An arbitrary value."),
 *   list_class = "\Drupal\Core\Field\FieldItemList",
 *   no_ui = TRUE,
 *   cardinality = \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
 * )
 *
 * @property string id
 * @property string type
 * @property string label
 * @property string description
 * @property mixed value
 * @property \Drupal\Component\Plugin\Context\ContextInterface context
 */
final class ContextItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $definitions = [];

    $definitions['id'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel(new TranslatableMarkup('Context ID'));

    $definitions['type'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel(new TranslatableMarkup('Data type'));

    $definitions['label'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel(new TranslatableMarkup('Label'));

    $definitions['description'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Description'));

    $definitions['value'] = DataDefinition::create('any')
      ->setRequired(TRUE)
      ->setLabel(new TranslatableMarkup('Value'));

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'id' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'type' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'label' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'description' => [
          'type' => 'text',
          'size' => 'normal',
        ],
        'value' => [
          'type' => 'blob',
          'size' => 'normal',
          'serialize' => TRUE,
          'not null' => TRUE,
        ],
      ],
    ];
  }

}
