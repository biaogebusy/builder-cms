<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_plugin_pack_restricted_editing\EventSubscriber;

use Drupal\ckeditor5_plugin_pack_restricted_editing\Utility\PermissionHelper;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * CKEditor 5 Plugin Pack Restricted Editing event subscriber.
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
   * The permission helper.
   *
   * @var \Drupal\ckeditor5_plugin_pack_restricted_editing\Utility\PermissionHelper
   */
  protected $permissionHelper;

  /**
   * Constructs a ConfigSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManager
   *   The entity type manager
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManager $entity_type_manager, PermissionHelper $permission_helper) {
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
    $plugin = 'restrictedEditing';

    // Exit if restricted editing wasn't enabled.
    $originalEditorSettings = $original['settings'] ?? [];
    $originalPlugins = isset($originalEditorSettings['toolbar']) ? array_keys($originalEditorSettings['toolbar']) : [];
    // Load current settings
    if (!in_array($plugin, $originalPlugins)) {
      return;
    }

    // Revoke restricted editing permission in case the plugin was removed.
    $editorSettings = $config->get('settings');
    $plugins = $editorSettings['toolbar'] ? array_keys($editorSettings['toolbar']) : [];
    if (!in_array($plugin, $plugins)) {
      $formatId = $config->get('format');
      $formats = $this->entityTypeManager->getStorage('filter_format')->loadByProperties(['status' => TRUE]);
      if (!isset($formats[$formatId])) {
        return;
      }
      $this->permissionHelper->revokeRestrictedEditingPermission([$formats[$formatId]]);
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
