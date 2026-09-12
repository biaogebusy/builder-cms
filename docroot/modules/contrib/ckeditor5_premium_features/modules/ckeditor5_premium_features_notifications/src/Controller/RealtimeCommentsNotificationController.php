<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Controller;

use Drupal\ckeditor5_premium_features_realtime_collaboration\Utility\NotificationIntegrator;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles the instant realtime notification after comment is submitted.
 *
 * @internal
 *   Controller classes are internal.
 */
class RealtimeCommentsNotificationController extends ControllerBase {

  /**
   * Constructs a new RealtimeCommentsNotificationController.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Drupal\ckeditor5_premium_features_realtime_collaboration\Utility\NotificationIntegrator $notificationIntegrator
   *   Notification integrator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *   Entity type manager.
   */
  public function __construct(protected RequestStack $requestStack,
                              protected NotificationIntegrator $notificationIntegrator,
                              protected EntityTypeManagerInterface $entityManager) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('ckeditor5_premium_features_realtime_collaboration.notification_integrator'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Check if given channel entity already exists in database.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response.
   */
  public function checkChannel(Request $request): Response {
    $channel = $this->requestStack->getCurrentRequest()?->attributes->get('channel');
    $entity = $this->entityManager->getStorage('ckeditor5_channel')?->load($channel);

    if ($entity) {
      return new Response("true", 200);
    }
    return new Response("false", 200);
  }

  /**
   * Passes the comment data for further processing.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   */
  public function send(Request $request): Response {
    $postData = $request->getContent();

    if (!$postData) {
      return new Response(null, 400);
    }

    $data = Json::decode($postData);

    $this->notificationIntegrator->handleInstantCommentNotification($data);

    return new Response("success", 200);
  }

  /**
   * Access handler.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user object.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Access result for current user.
   */
  public function access(AccountInterface $account): AccessResult {
    $channel = $this->requestStack->getCurrentRequest()?->attributes->get('channel');
    if (!$channel) {
      return AccessResult::forbidden("Missing channel argument.");
    }

    $entities = $this->entityTypeManager()->getStorage($channel->get('entity_type')->value)->loadByProperties(['uuid' => $channel->get('entity_id')->value]);
    if (empty($entities)) {
      return AccessResult::forbidden("Target entity does not exist.");
    }

    $entity = reset($entities);

    return AccessResult::allowedIf($entity->access('update', $account));
  }

}
