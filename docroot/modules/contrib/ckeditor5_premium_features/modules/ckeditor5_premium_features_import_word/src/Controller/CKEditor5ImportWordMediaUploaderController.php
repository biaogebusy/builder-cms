<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_import_word\Controller;

use Drupal\ckeditor5_premium_features\CKeditorPremiumLoggerChannelTrait;
use Drupal\ckeditor5_premium_features_import_word\Utility\ImportWordMediaUploader;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns response for Import Word media upload.
 *
 * @internal
 *   Controller classes are internal.
 */
class CKEditor5ImportWordMediaUploaderController extends ControllerBase {

  use CKeditorPremiumLoggerChannelTrait;

  /**
   * Constructs a new CKEditor5ImportWordMediaUploaderController.
   *
   * @param \Drupal\ckeditor5_premium_features_import_word\Utility\ImportWordMediaUploader $importWordMediaUploader
   *   Import Word media uploader.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   */
  public function __construct(protected ImportWordMediaUploader $importWordMediaUploader, protected RequestStack $requestStack) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ckeditor5_premium_features_import_word.media_uploader'),
      $container->get('request_stack'),
    );
  }

  /**
   * Uploads image and creates media entity.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON object including the media uuid or error message.
   */
  public function upload(Request $request) {
    $editor = $request->attributes->get('editor');
    $content = JSON::decode($request->getContent());
    $base64Data = $content['image'] ?? NULL;
    if (!$base64Data) {
      return new JsonResponse(['error' => 'Wrong base64 data provided'], 400);
    }
    try {
      $mediaUuid = $this->importWordMediaUploader->createMedia($editor, $base64Data);
    }
    catch (\Exception $exception) {
      $this->logException('An error occurred while creating media entity from Word document', $exception);
      return new JsonResponse(['error' => 'Something went wrong'], 400);
    }

    return new JsonResponse(['mediaUuid' => $mediaUuid], 200);
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
    /** @var \Drupal\editor\Entity\Editor $editor */
    $editor = $this->requestStack->getCurrentRequest()?->attributes->get('editor');
    if (!$editor) {
      return AccessResult::forbidden("Missing editor argument.");
    }

    $settings = $editor->getSettings();
    $importWordSettings = $settings["plugins"]["ckeditor5_premium_features_import_word__import_word"] ?? NULL;
    if (!$importWordSettings) {
      return AccessResult::forbidden("Missing Import from Word plugin settings.");
    }

    $mediaBundle = $importWordSettings["upload_media"]["media_bundle"] ?? NULL;
    if (!$mediaBundle) {
      return AccessResult::forbidden("Missing Media bundle setting.");
    }

    $permission = "create " . $mediaBundle . " media";

    return AccessResult::allowedIf($account->hasPermission($permission), "User doesn't have permission to create specified media entity.");
  }

}
