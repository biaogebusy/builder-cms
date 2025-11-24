<?php

namespace Drupal\eck\Plugin\migrate\source\d7;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 7 ECK entity bundle source plugin.
 *
 * For additional configuration keys, refer to the parent classes:
 *
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 *
 * @MigrateSource(
 *   id = "d7_eck_entity_bundle",
 *   source_module = "eck"
 * )
 */
class EckEntityBundle extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('eck_bundle', 'eckb')->fields('eckb');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('The primary identifier for a bundle'),
      'entity_type' => $this->t('The entity type this bundle belongs to'),
      'name' => $this->t('The bundle name'),
      'label' => $this->t('A human readable name for the bundle (note that the type is not human readable)'),
      'config' => $this->t('A serialized list of the bundle settings'),
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
