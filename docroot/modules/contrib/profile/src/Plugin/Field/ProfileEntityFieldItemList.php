<?php

namespace Drupal\profile\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\user\UserInterface;

/**
 * Represents a profile entity reference field.
 */
class ProfileEntityFieldItemList extends FieldItemList implements EntityReferenceFieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    foreach ($this->getUserProfiles() as $delta => $value) {
      $this->list[$delta] = $this->createItem($delta, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function referencedEntities() {
    return $this->getUserProfiles();
  }

  /**
   * Get the user's profiles.
   *
   * @return array|\Drupal\profile\Entity\ProfileInterface[]
   *   An array of profiles.
   */
  protected function getUserProfiles() {
    $user = $this->getEntity();
    assert($user instanceof UserInterface);
    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');

    // Ignore anonymous and user accounts not saved yet.
    if ($user->isAnonymous() || $user->isNew()) {
      return [];
    }

    $profiles = $profile_storage->loadMultipleByUser($user, $this->getSetting('profile_type'), TRUE);

    // This will renumber the keys while preserving the order of elements.
    $profiles = array_values($profiles);

    return $profiles;
  }

}
