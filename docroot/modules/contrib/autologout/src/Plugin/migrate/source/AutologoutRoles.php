<?php

namespace Drupal\autologout\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 6 and 7 Autologout source.
 *
 * @MigrateSource(
 *   id = "autologout_roles",
 *   source_module = "autologout",
 * )
 */
class AutologoutRoles extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('variable', 'v')
      ->fields('v')
      ->condition('name', 'autologout_role_%_timeout', 'LIKE');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'enabled' => $this->t('Autologout user role is enabled.'),
      'timeout' => $this->t('Autologout user role timeout.'),
      'role' => $this->t('Autologout user role.'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $row_name = str_replace('_timeout', '', $row->getSourceProperty('name'));
    $timeout = unserialize($row->getSourceProperty('value'));
    $query = $this->select('variable', 'v')
      ->fields('v', ['value'])
      ->condition('name', $row_name)
      ->execute()
      ->fetchAssoc();
    $enabled = unserialize($query['value']);

    $row_name = str_replace('_timeout', '', $row->getSourceProperty('name'));
    $rid = explode('_', $row_name)[2];
    $query_roles = $this->select('role', 'r')
      ->fields('r', ['name'])
      ->condition('rid', $rid)
      ->execute()
      ->fetchAssoc();
    $role = $query_roles['name'];

    $row->setSourceProperty('enabled', (bool) $enabled);
    $row->setSourceProperty('timeout', (int) $timeout);
    $row->setSourceProperty('role', $role);

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['name']['type'] = 'string';
    $ids['value']['type'] = 'string';
    return $ids;
  }

}
