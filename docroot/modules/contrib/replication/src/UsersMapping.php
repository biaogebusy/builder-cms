<?php

namespace Drupal\replication;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;

class UsersMapping {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Returns UID from replication.settings config object.
   *
   * @return int
   */
  public function getUidFromConfig() {
    return $this->configFactory->get('replication.settings')->get('uid');
  }

  /**
   * Maps user reference field.
   *
   * @param array $entity
   * @param string $field_name
   *
   * @return array
   */
  public function mapReferenceField($entity, $field_name) {
    $field_info = [];
    foreach ($entity[$field_name] as $delta => $item) {
      $users = [];
      if (isset($item['username'])) {
        $users = $this->entityTypeManager->getStorage('user')
          ->loadByProperties(['name' => $item['username']]);
      }
      $user = reset($users);
      if ($user instanceof UserInterface && $id = $user->id()) {
        $field_info[$delta] = ['target_id' => $id];
      }
      else {
        $field_info[$delta] = ['target_id' => $this->getUidFromConfig()];
      }
    }
    return $field_info;
  }

}
