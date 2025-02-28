<?php

namespace Drupal\charts\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'chart_config' field type.
 *
 * @FieldType(
 *   id = "chart_config",
 *   label = @Translation("Chart"),
 *   description = @Translation("An entity field containing data for a chart item"),
 *   default_widget = "chart_config_default",
 *   default_formatter = "chart_config_default"
 * )
 */
class ChartConfigItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['config'] = DataDefinition::create('chart_config')
      ->setLabel(new TranslatableMarkup('Chart configuration'))
      ->setRequired(TRUE);
    $properties['library'] = DataDefinition::create('string')
      ->setLabel(t('Chart library'));
    $properties['type'] = DataDefinition::create('string')
      ->setLabel(t('Chart type'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'config';
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'config' => [
          'type' => 'blob',
          'size' => 'big',
          'not null' => TRUE,
          'serialize' => TRUE,
        ],
        'library' => [
          'description' => 'The chart library.',
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
        ],
        'type' => [
          'description' => 'The chart type.',
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->config);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (is_array($values['config'])) {
      $values += [
        'library' => $values['config']['library'] ?? NULL,
        'type' => $values['config']['type'] ?? NULL,
      ];
    }

    parent::setValue($values, $notify);
  }

}
