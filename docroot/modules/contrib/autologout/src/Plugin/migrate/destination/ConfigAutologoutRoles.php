<?php

namespace Drupal\autologout\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\Config;
use Drupal\migrate\Row;

/**
 * Autologout Configuration Migration.
 *
 * @MigrateDestination(
 *   id = "config:autologout"
 * )
 */
class ConfigAutologoutRoles extends Config {

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    $autologout_role = 'autologout.role.';
    $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
    foreach ($roles as $role) {
      if (strtolower($row->getSourceProperty('role')) === strtolower($role->label())) {
        $autologout_role = 'autologout.role.' . $role->id();
        $this->config->setName($autologout_role);
        $this->config->save();
        break;
      }
    }

    $entity_ids = parent::import($row, $old_destination_id_values);
    $entity_ids[0] = $autologout_role;

    return $entity_ids;
  }

}
