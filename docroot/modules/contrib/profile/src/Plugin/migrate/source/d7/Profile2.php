<?php

namespace Drupal\profile\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Profile2 source from database.
 *
 * @MigrateSource(
 *   id = "d7_profile2",
 *   source_module = "profile2"
 * )
 */
class Profile2 extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('profile', 'p')
      ->fields('p')
      ->distinct()
      ->orderBy('pid');
    // Skip profiles with invalid users.
    $query->innerJoin('users', 'u', 'p.uid = u.uid');

    if (isset($this->configuration['bundle'])) {
      $query->condition('p.type', (array) $this->configuration['bundle'], 'IN');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'pid' => $this->t('Primary Key: Unique profile item ID.'),
      'type' => $this->t('The {profile_type}.type of this profile.'),
      'uid' => $this->t('The {users}.uid of the associated user.'),
      'label' => $this->t('A human-readable label for this profile.'),
      'created' => $this->t('The Unix timestamp when the profile was created.'),
      'changed' => $this->t("The Unix timestamp when the profile was most recently saved."),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Get Field API field values.
    foreach (array_keys($this->getFields('profile2', $row->getSourceProperty('type'))) as $field) {
      $tid = $row->getSourceProperty('pid');
      $row->setSourceProperty($field, $this->getFieldValues('profile2', $field, $tid));
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['pid']['type'] = 'integer';
    return $ids;
  }

}
