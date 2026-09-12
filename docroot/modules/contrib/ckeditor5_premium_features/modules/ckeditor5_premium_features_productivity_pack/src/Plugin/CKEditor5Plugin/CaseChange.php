<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_productivity_pack\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features\Plugin\PremiumFeaturesPluginDefinitionInterface;
use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\ckeditor5_premium_features\Utility\PluginHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 Productivity Pack Case Change Plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class CaseChange extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface, CKEditor5PluginConfigurableInterface, PremiumFeaturesPluginDefinitionInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * The id of the plugin in productivity pack.
   */
  const PRODUCTIVITY_PACK_PLUGIN_ID = 'caseChange';

  /**
   * Creates the plugin instance.
   *
   * @param \Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker $libraryVersionChecker
   *   Helper for checking CKEditor 5 version.
   * @param \Drupal\ckeditor5_premium_features\Utility\PluginHelper $pluginHelper
   *   Helper for getting the editor toolbar plugins.
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(
    protected LibraryVersionChecker $libraryVersionChecker,
    protected PluginHelper $pluginHelper,
    ...$parent_arguments) {
    parent::__construct(...$parent_arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ckeditor5_premium_features.core_library_version_checker'),
      $container->get('ckeditor5_premium_features.plugin_helper'),
      $configuration,
      $plugin_id,
      $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $plugin = $this->getFeaturedPluginId();
    $titleCaseExcludedWords = $this->configuration['title_case_exclude_words'];
    if ($titleCaseExcludedWords) {
      $titleCaseExcludedArr = explode(',', preg_replace('/\s+/', '', $titleCaseExcludedWords));
      $titleCaseExcludedArr = array_filter($titleCaseExcludedArr, fn($word) => !empty($word));
      $static_plugin_config[$plugin]['titleCase']['excludeWords'] = $titleCaseExcludedArr;
    }

    return $static_plugin_config;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'title_case_exclude_words' => '',
    ];
  }

  /**
   * Gets the featured plugin id.
   *
   * @return string
   *   The CKEditor plugin name.
   */
  public function getFeaturedPluginId(): string {
    return self::PRODUCTIVITY_PACK_PLUGIN_ID;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $isValidCKE5Version = $this->libraryVersionChecker->isLibraryVersionHigherOrEqual('41.0.0');
    if (!$isValidCKE5Version) {
      $form['info'] = [
        '#type' => 'container',
        '#markup' => $this->t('The CKEditor 5 Case Change is available since version 41.0.0. CKEditor 5 v41.x was introduced in Drupal 10.3. Please update your Drupal core to use this feature.'),
      ];
    }
    $form['title_case_exclude_words'] = [
      '#title' => $this->t('The Title Case excluded words.'),
      '#type' => 'textarea',
      '#description' => $this->t('Words that should not be capitalized.<br /><br />
          <b>Example:</b> a, an, and, as, at, but, by, for.'),
      '#default_value' => $this->configuration['title_case_exclude_words'],
      '#access' => $isValidCKE5Version,
    ];

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $toolbars = $this->pluginHelper->getFormToolbars($form_state);

    if (in_array('caseChange', $toolbars) && !$this->libraryVersionChecker->isLibraryVersionHigherOrEqual('41.0.0')) {
      $form_state->setErrorByName('editor', $this->t('The CKEditor 5 Case Change is available since version 41.0.0. CKEditor 5 v41.x was introduced in Drupal 10.3. Please update your Drupal core to use this feature.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['title_case_exclude_words'] = $form_state->getValue('title_case_exclude_words');
  }

}
