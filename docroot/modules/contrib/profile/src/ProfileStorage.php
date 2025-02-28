<?php

namespace Drupal\profile;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the entity storage for profile.
 */
class ProfileStorage extends SqlContentEntityStorage implements ProfileStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function loadMultipleByUser(AccountInterface $account, $profile_type_id, $published = TRUE) {
    $query = $this->getQuery();
    $query
      ->condition('uid', $account->id())
      ->condition('type', $profile_type_id)
      ->condition('status', $published)
      ->sort('is_default', 'DESC')
      ->sort('profile_id', 'DESC');
    $result = $query->accessCheck(FALSE)->execute();

    return $result ? $this->loadMultiple($result) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function loadByUser(AccountInterface $account, $profile_type_id) {
    $query = $this->getQuery();
    $query
      ->condition('uid', $account->id())
      ->condition('type', $profile_type_id)
      ->condition('status', TRUE)
      ->sort('is_default', 'DESC')
      ->sort('profile_id', 'DESC')
      ->range(0, 1);
    $result = $query->accessCheck(FALSE)->execute();

    return $result ? $this->load(reset($result)) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadDefaultByUser(AccountInterface $account, $profile_type_id) {
    $result = $this->loadByProperties([
      'uid' => $account->id(),
      'type' => $profile_type_id,
      'status' => TRUE,
      'is_default' => TRUE,
    ]);

    return !empty($result) ? reset($result) : NULL;
  }

}
