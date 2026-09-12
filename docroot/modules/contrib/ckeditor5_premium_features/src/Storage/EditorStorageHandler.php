<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Storage;

use Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface;
use Drupal\ckeditor5_premium_features\Plugin\CKEditor5Plugin\CollaborationBase;
use Drupal\ckeditor5_premium_features_productivity_pack\Plugin\CKEditor5Plugin\DocumentOutline;
use Drupal\ckeditor5_premium_features_productivity_pack\Plugin\CKEditor5Plugin\ProductivityPackBase;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\editor\EditorInterface;

/**
 * Provides the handler of the editor storage.
 *
 * This handler allows to detect the usage of the ckeditor5
 * and collaboration features.
 */
class EditorStorageHandler implements EditorStorageHandlerInterface {

  use StringTranslationTrait;

  public const SUPPORTED_EDITOR_ID = 'ckeditor5';

  /**
   * The editor entity storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected ConfigEntityStorageInterface $editorStorage;

  /**
   * Creates the handler instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    protected SettingsConfigHandlerInterface $settingsConfigHandler,
    protected AccountProxyInterface $account,
    protected MessengerInterface $messenger
  ) {
    $this->editorStorage = $entity_type_manager->getStorage('editor');
  }

  /**
   * {@inheritdoc}
   */
  public function isCkeditor5(array $element): bool {
    $editors = $this->getAllEditorsFromElement($element);

    foreach ($editors as $editor) {
      if ($editor?->getEditor() === static::SUPPORTED_EDITOR_ID) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasCollaborationFeaturesEnabled(array $element, bool $allEditors = TRUE): bool {
    if (!$this->isCkeditor5($element)) {
      // Don't process if this is not a CKEditor5.
      return FALSE;
    }

    $editors = $allEditors ? $this->getAllEditorsFromElement($element) : [$this->getEditorFromElement($element)];

    $toolbar_items = [];
    $filterLabels = [];
    foreach ($editors as $editor) {
      if ($editor) {
        $toolbar_items = array_merge($toolbar_items, $editor->getSettings()['toolbar']['items'] ?? []);
        if (array_intersect($editor->getSettings()['toolbar']['items'] ?? [], CollaborationBase::getToolbars())) {
          if ($this->account->hasPermission('use ckeditor5 access token')
            && $this->settingsConfigHandler->isApiKeyRequired()
            && !$this->settingsConfigHandler->getApiKey()) {
            $filterLabels[] = $editor->getFilterFormat()->label();
          }
        }
      }
    }
    if (!empty($filterLabels)) {
      $textFormatsLabels[] = implode(' and ', array_splice($filterLabels, -2));
      $this->messenger->addWarning(
        $this->t('Invalid configuration for CKEditor5 premium features. Missing API Key for Text formats: %text_formats </br> Check <a href="@config_url">Premium features configuration.</a>',
          [
            '%text_formats' => implode(', ', $textFormatsLabels),
            '@config_url' => '/admin/config/ckeditor5-premium-features/settings',
          ]
        )
      );
    }

    return (bool) array_intersect($toolbar_items, CollaborationBase::getToolbars());
  }

  /**
   * {@inheritdoc}
   */
  public function hasDocumentOutlineFeaturesEnabled(array $element): bool {
    $editors = $this->getAllEditorsFromElement($element);

    foreach ($editors as $editor) {
      if ($editor && $editor->getEditor() == static::SUPPORTED_EDITOR_ID) {
        $plugins = $editor->getSettings()['plugins'];
        if (isset($plugins[ProductivityPackBase::PLUGIN_CONFIG_NAME]) && $plugins[ProductivityPackBase::PLUGIN_CONFIG_NAME][DocumentOutline::CONFIG_FIELD_ENABLED]) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Gets the editor entity from the element format.
   *
   * @param array $element
   *   The form element with the editor format defined.
   *
   * @return \Drupal\editor\EditorInterface|null
   *   The editor entity instance.
   */
  private function getEditorFromElement(array $element): ?EditorInterface {
    $format = $element['#format'] ?? NULL;
    if (!$format) {
      return NULL;
    }

    return $this->editorStorage->load($format);
  }

  /**
   * Gets all possible editor entities from the element format.
   *
   * @param array $element
   *   The form element with the editor format defined.
   *
   * @return \Drupal\editor\EditorInterface[]|null
   *   The editor entities array.
   */
  private function getAllEditorsFromElement(array $element): ?array {
    $formats = $element['format']['format']['#options'] ?? [];
    if (empty($formats)) {
      return [];
    }

    return $this->editorStorage->loadMultiple(array_keys($formats));
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackChangesStates(array $element, bool $rtc = FALSE): array {
    $editors = $this->getAllEditorsFromElement($element);
    $states = [];
    $module_name = 'ckeditor5_premium_features_collaboration';
    if ($rtc) {
      $module_name = 'ckeditor5_premium_features_realtime_collaboration';
    }
    $plugin_name = 'track_changes';

    /**
     * @var \Drupal\editor\Entity\Editor $editor
     */
    foreach ($editors as $editor) {
      if (!$this->isCkeditor5($element)) {
        continue;
      }
      $settings = $editor->getSettings();
      $default_state = $settings['plugins'][$module_name . '__' . $plugin_name]['default_state'] ?? FALSE;
      $states[$editor->id()] = $default_state;
    }
    return $states;
  }

}
