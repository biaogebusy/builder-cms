<?php

namespace Drupal\commerce\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'commerce_remote_id' field type.
 *
 * @property string $provider
 * @property string $remote_id
 */
#[FieldType(
  id: "commerce_remote_id",
  label: new TranslatableMarkup("Remote ID"),
  description: new TranslatableMarkup("Stores remote IDs."),
  category: "commerce",
  default_widget: "",
  default_formatter: "commerce_remote_id_default",
  list_class: RemoteIdFieldItemList::class,
)]
class RemoteIdItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['provider'] = DataDefinition::create('string')
      ->setLabel(t('Provider'));
    $properties['remote_id'] = DataDefinition::create('string')
      ->setLabel(t('Remote ID'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'provider' => [
          'description' => 'The provider.',
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
        ],
        'remote_id' => [
          'description' => 'The remote ID.',
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
  public static function mainPropertyName() {
    return 'remote_id';
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->provider) || $this->remote_id === NULL || $this->remote_id === '';
  }

}
