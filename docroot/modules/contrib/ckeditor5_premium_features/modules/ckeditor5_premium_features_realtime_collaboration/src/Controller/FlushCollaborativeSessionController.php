<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Controller;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ckeditor5_premium_features\Utility\ApiAdapter;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for CKEditor 5 Premium Features Realtime Collaboration
 * routes.
 */
class FlushCollaborativeSessionController extends ControllerBase {

  /**
   * The controller constructor.
   *
   * @param \Drupal\ckeditor5_premium_features\Utility\ApiAdapter $apiAdapter
   *   The API adapter service.
   */
  public function __construct(protected ApiAdapter $apiAdapter) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ckeditor5_premium_features.api_adapter'),
    );
  }

  /**
   * Builds the response.
   *
   * @param string $documentId
   *   The document (channel) id to be flushed.
   */
  public function flush(string $documentId) :Response {
    $this->apiAdapter->flushCollaborativeSession($documentId);

    return new Response('Session flushed', 200);
  }

  /**
   * Access callback for the controller
   *
   * @param string $documentId
   *   The document (channel) id to be flushed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current user account.
   */
  public function access(string $documentId, AccountInterface $account) :AccessResultInterface {
    /** @var \Drupal\ckeditor5_premium_features_cloud_services\Entity\ChannelInterface $channel */
    $channel = $this->entityTypeManager()->getStorage('ckeditor5_channel')->load($documentId);

    if (!$channel) {
      return AccessResult::allowed();
    }

    // Verify that user have access to edit the node containing field with given channel id. In case there is no channel
    // then we're dealing with new node, so allow flushing operation.
    $entityUuid = $channel->getTargetEntityUuid();
    $entityType = $channel->getTargetEntityType();

    $entities = $this->entityTypeManager->getStorage($entityType)->loadByProperties(['uuid' => $entityUuid]);
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = reset($entities);
    if ($entity instanceof AccessibleInterface) {
      return AccessResult::allowedIf($entity->access('edit', $account));
    }

    return AccessResult::forbidden();
  }

}
