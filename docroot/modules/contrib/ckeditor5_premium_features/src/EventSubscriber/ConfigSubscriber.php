<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\EventSubscriber;

use Drupal\ckeditor5_premium_features\CollaborationPermissions;
use Drupal\ckeditor5_premium_features\Utility\PermissionHelper;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * CKEditor 5 Premium Features event subscriber.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The permissions helper.
   *
   * @var \Drupal\ckeditor5_premium_features\Utility\PermissionHelper
   */
  protected $permissionHelper;

  /**
   * Constructs a ConfigSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, PermissionHelper $permission_helper) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->permissionHelper = $permission_helper;
  }

  /**
   * Config save response event handler.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent
   *   Response event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    $isEditorConfig = strpos($config->getName(), 'editor.editor');
    if ($isEditorConfig !== 0 || $config->isNew()) {
      return;
    }
    if (!$original = $config->getOriginal()) {
      return;
    }
    $premiumPlugins = [
      'ckeditor5_premium_features_collaboration__comments',
      'ckeditor5_premium_features_collaboration__track_changes',
      'ckeditor5_premium_features_realtime_collaboration__comment',
      'ckeditor5_premium_features_realtime_collaboration__track_changes',
    ];

    // Exit if there was no collaboration plugins before.
    $originalEditorSettings = $original['settings'] ?? [];
    $originalPlugins = isset($originalEditorSettings["plugins"]) ? array_keys($originalEditorSettings["plugins"]) : [];
    if (empty(array_intersect($originalPlugins, $premiumPlugins))) {
      return;
    }

    // Revoke all collaboration permissions in case there is no collaboration
    // plugins after save.
    $editorSettings = $config->get('settings');
    $plugins = $editorSettings["plugins"] ? array_keys($editorSettings["plugins"]) : [];
    if (empty(array_intersect($plugins, $premiumPlugins))) {
      $formatId = $config->get('format');
      $formats = $this->entityTypeManager->getStorage('filter_format')->loadByProperties(['status' => TRUE]);
      if (!isset($formats[$formatId])) {
        return;
      }
      $this->permissionHelper->revokeCollaborationPermissions([$formats[$formatId]]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => ['onConfigSave'],
    ];
  }

}
