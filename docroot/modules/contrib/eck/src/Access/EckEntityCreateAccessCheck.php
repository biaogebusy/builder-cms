<?php

namespace Drupal\eck\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eck\EckEntityTypeInterface;

/**
 * Determines access for ECK entity add page.
 */
class EckEntityCreateAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an EckEntityCreateAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks access to the eck entity add page for the entity bundle type.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Drupal\eck\EckEntityTypeInterface $eck_entity_type
   *   The entity type.
   * @param string $eck_entity_bundle
   *   (optional) The entity type bundle.
   *
   * @return bool|AccessResult|\Drupal\Core\Access\AccessResultInterface
   *   A \Drupal\Core\Access\AccessInterface constant value.
   */
  public function access(AccountInterface $account, EckEntityTypeInterface $eck_entity_type, $eck_entity_bundle = NULL) {
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler($eck_entity_type->id());
    if (!empty($eck_entity_bundle)) {
      return $access_control_handler->createAccess($eck_entity_bundle, $account, [], TRUE);
    }
    // Get the entity type bundles.
    $bundles = $this->entityTypeManager->getStorage($eck_entity_type->id() . '_type')
      ->loadMultiple();

    // If checking whether an entity of any type may be created.
    foreach ($bundles as $eck_entity_bundle) {
      if (($access = $access_control_handler->createAccess($eck_entity_bundle->id(), $account, [], TRUE)) && $access->isAllowed()) {
        return $access;
      }
    }

    // No opinion.
    return AccessResult::neutral();
  }

}
