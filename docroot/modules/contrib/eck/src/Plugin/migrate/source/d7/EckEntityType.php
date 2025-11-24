<?php

namespace Drupal\eck\Plugin\migrate\source\d7;

use Drupal\Component\Serialization\Json;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 7 ECK Entity types source from database.
 *
 * For additional configuration keys, refer to the parent classes:
 *
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 *
 * @MigrateSource(
 *   id = "d7_eck_entity_type",
 *   source_module = "eck"
 * )
 */
class EckEntityType extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('eck_entity_type', 'ecket')->fields('ecket');
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Properties array is JSON encoded, so we need to decode it.
    $properties = Json::decode($row->getSourceProperty('properties'));
    foreach ($properties as $key => $property) {
      // We take advantage of the fact that the properties key corresponds to
      // the Drupal 8 eck.eck_entity_type properties.
      // Value is not important, as the choice is between enabled or not, and
      // only enabled values are present in the properties array.
      $row->setSourceProperty($key, TRUE);
    }
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('The primary identifier for a bundle'),
      'name' => $this->t('The machine name of the entity'),
      'label' => $this->t('The entity label'),
      'properties' => $this->t('A serialized list of properties attached to this entity'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
      ],
    ];
  }

}
