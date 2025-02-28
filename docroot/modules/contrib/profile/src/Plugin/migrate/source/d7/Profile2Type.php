<?php

namespace Drupal\profile\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 7 profile2 source from database.
 *
 * @MigrateSource(
 *   id = "d7_profile2_type",
 *   source_module = "profile2"
 * )
 */
class Profile2Type extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('profile_type', 'pt')
      ->fields('pt', [
        'id',
        'type',
        'label',
        'weight',
        'data',
        'status',
        'module',
      ]);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('Primary Key: Unique profile type ID.'),
      'type' => $this->t('The machine-readable name of this profile type.'),
      'label' => $this->t('The human-readable name of this profile type.'),
      'weight' => $this->t('The weight of this profile type in relation to others.'),
      'data' => $this->t('A serialized array of additional data related to this profile type.'),
      'status' => $this->t('The exportable status of the entity.'),
      'module' => $this->t('The name of the providing module if the entity has been defined in code.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $data = unserialize($row->getSourceProperty('data'));

    $row->setSourceProperty('registration', $data['registration']);

    if (isset($data['use_page'])) {
      $row->setSourceProperty('use_page', $data['use_page']);
    }

    if (isset($data['roles'])) {
      $row->setSourceProperty('roles', array_filter($data['roles']));
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['id']['type'] = 'integer';
    return $ids;
  }

}
