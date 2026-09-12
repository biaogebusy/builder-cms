<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the common access handler for the collaboration entities.
 */
class CollaborationEntityAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * Constructs a access control handler instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface|CollaborationEntityInterface $entity, $operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $this->prepareUser($account);

    if ($operation === 'delete') {
      $result = AccessResult::forbidden("The delete operation is not allowed.");
      return $return_as_object ? $result : $result->isAllowed();
    }

    if (!$account->hasPermission('access content')) {
      $result = AccessResult::forbidden("The 'access content' permission is required.")->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }

    $entity_type = $entity->getEntityTypeTargetId();
    $entity_id = $entity->getEntityId();

    if ($operation === 'view_new') {
      $result = AccessResult::allowed();
      return $return_as_object ? $result : $result->isAllowed();
    }

    try {
      $storage = $this->entityTypeManager->getStorage($entity_type);
      $related_entity = $storage->loadByProperties(['uuid' => $entity_id]);
      $related_entity = reset($related_entity);
      if (!$related_entity instanceof EntityInterface) {
        throw new \Exception();
      }
      $result = $related_entity->access($operation, $account, TRUE);
    }
    catch (\Throwable) {
      $result = AccessResult::forbidden("Invalid entity type defined. The access can't be verified.");
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function createAccess($entity_bundle = NULL, ?AccountInterface $account = NULL, array $context = [], $return_as_object = FALSE) {
    // Since currently there is no policy limiting access to.
    $result = AccessResult::allowed();
    return $return_as_object ? $result : $result->isAllowed();
  }

}
