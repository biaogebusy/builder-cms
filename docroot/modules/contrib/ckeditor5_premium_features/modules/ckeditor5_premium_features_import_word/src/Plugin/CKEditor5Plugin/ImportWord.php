<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_import_word\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\ckeditor5_premium_features_import_word\Config\ImportWordConfigHandlerInterface;
use Drupal\ckeditor5_premium_features_import_word\Utility\ImportWordMediaUploader;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 "Import from Word" plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class ImportWord extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface, ContainerFactoryPluginInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * Creates the plugin instance.
   *
   * @param \Drupal\ckeditor5_premium_features_import_word\Config\ImportWordConfigHandlerInterface $configHandler
   *   The settings configuration handler.
   * @param \Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker $libraryVersionChecker
   *   CKEditor 5 library checker.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity bundle info provider.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param bool $isMediaEnabled
   *   The state of Media module.
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(
    protected ImportWordConfigHandlerInterface $configHandler,
    protected LibraryVersionChecker $libraryVersionChecker,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected bool $isMediaEnabled,
    ...$parent_arguments
  ) {
    parent::__construct(...$parent_arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('ckeditor5_premium_features_import_word.config_handler.settings'),
      $container->get('ckeditor5_premium_features.core_library_version_checker'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
      $container->get('module_handler')->moduleExists('media'),
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'upload_media' => [
        'enabled' => FALSE,
        'media_bundle' => '',
        'media_field_name' => '',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {

    $mediaBundles = $this->getMediaBundles();

    $form['upload_media']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable uploading images as a Drupal Media'),
      '#attributes' => [
        'data-editor-word-media-upload' => 'status',
      ],
      '#default_value' => $this->configuration['upload_media']['enabled'] ?? FALSE,
      '#description' => $this->t('Enable uploading images from a Word document as Drupal Media. <br /> Please be aware that enabling this feature will make imports longer.'),
    ];
    if (!$this->isMediaEnabled) {
      $form['upload_media']['enabled']['#attributes']['disabled'] = 'disabled';
    }

    $defaultMediaBundle = $this->configuration['upload_media']['media_bundle'] ?? '';
    $defaultFieldName = $this->configuration['upload_media']['media_field_name'] ?? '';
    $form['upload_media']['media_bundle'] = [
      '#type' => 'select',
      '#options' => $mediaBundles,
      '#title' => $this->t('Choose media bundle and field'),
      '#default_value' => $defaultMediaBundle . ':' . $defaultFieldName,
      '#description' => $this->t('The image will be added to the selected media field. <br /> The roles able to use the import plugin need to have permission to create selected media bundles. Otherwise, it will create regular &lt;img&gt; tags.'),
      '#states' => [
        'visible' => [
          ':input[data-editor-word-media-upload="status"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[data-editor-word-media-upload="status"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['upload_media']['image_destination_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Choose image destination directory'),
      '#default_value' => $this->configuration['upload_media']['image_destination_dir'] ?? ImportWordMediaUploader::DEFAULT_UPLOAD_DIR,
      '#description' => $this->t('Image will be placed into the provided directory.'),
      '#states' => [
        'visible' => [
          ':input[data-editor-word-media-upload="status"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config = parent::getDynamicPluginConfig($static_plugin_config, $editor);

    if ($this->libraryVersionChecker->isLibraryVersionHigherOrEqual('40.1.0')) {
      $isWordStylesEnabled = $this->configHandler->isWordStylesEnabled();
      $static_plugin_config['importWord']['formatting']['defaults'] = $isWordStylesEnabled ? 'inline' : 'none';
      $static_plugin_config['importWord']['formatting']['resets'] = $isWordStylesEnabled ? 'inline' : 'none';
    }
    else {
      $static_plugin_config['importWord']['defaultStyles'] = $this->configHandler->isWordStylesEnabled();
    }
    $settings = $editor->getSettings();
    $uploadMedia = $settings['plugins'][$this->pluginId]['upload_media'] ?? [];
    if (!empty($uploadMedia)) {
      $static_plugin_config['importWord']['uploadMedia']['enabled'] = $uploadMedia['enabled'];
    }

    if ($this->configHandler->hasConverterUrl()) {
      $static_plugin_config['importWord']['converterUrl'] = $this->configHandler->getConverterUrl();
    }
    if ($tokenUrl = $this->configHandler->getTokenUrl()) {
      if (!ckeditor5_premium_features_check_jwt_installed()) {
        $message = $this->t("Import from Word plugin is working in license key authentication mode because its required dependency <code>firebase/php-jwt</code> is not installed. This may result with limited functionality.");
        ckeditor5_premium_features_display_missing_dependency_warning($message);
      }
      else {
        $static_plugin_config['importWord']['tokenUrl'] = $tokenUrl;
      }
    }

    return $static_plugin_config;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $formValues = $form_state->getValues();
    $uploadMediaEnabled = $formValues['upload_media']['enabled'];
    $completeFormValues = $form_state->getCompleteFormState()->getValues();
    $mediaEmbedFilterEnabled = $completeFormValues['filters']['media_embed']['status'] ?? FALSE;

    if ($uploadMediaEnabled && !$mediaEmbedFilterEnabled) {
      $form_state->setError($form["upload_media"]["enabled"], "Embed Media filter has to be enabled in order to use uploading images as a Drupal Media on Import from Word feature.");
    }

  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $formValues = $form_state->getValues();
    $uploadMediaValue = $formValues['upload_media']['media_bundle'] ?? '';
    $mediaBundle = '';
    $mediaFieldName = '';
    if ($uploadMediaValue) {
      [$mediaBundle, $mediaFieldName] = explode(':', $uploadMediaValue);
    }
    $this->configuration['upload_media']['enabled'] = (bool) $formValues['upload_media']['enabled'];
    $this->configuration['upload_media']['media_bundle'] = $mediaBundle;
    $this->configuration['upload_media']['media_field_name'] = $mediaFieldName;
    $this->configuration['upload_media']['image_destination_dir'] = $formValues['upload_media']['image_destination_dir'] ?? ImportWordMediaUploader::DEFAULT_UPLOAD_DIR;
  }

  /**
   * Returns array of available bundles.
   *
   * @return array
   *   Media bundles.
   */
  private function getMediaBundles(): array {
    $mediaBundleDef = $this->entityTypeBundleInfo->getBundleInfo('media');
    $imageFieldTypes = $this->entityFieldManager->getFieldMapByFieldType('image');
    $imageFieldTypes = $imageFieldTypes['media'] ?? [];
    if (empty($imageFieldTypes)) {
      return [];
    }
    // Exclude default thumbnail images.
    unset($imageFieldTypes['thumbnail']);
    $bundles = [];
    foreach ($mediaBundleDef as $key => $value) {
      foreach ($imageFieldTypes as $fieldName => $field) {
        if (array_key_exists($key, $field['bundles'])) {
          $bundles[$key][$key . ':' . $fieldName] = '(' . $value['label'] . ') ' . $fieldName;
        }
      }
    }
    return $bundles;
  }

}
