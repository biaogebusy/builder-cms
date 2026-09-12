<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_source_editing_enhanced\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 Source Editing Enhanced plugin configuration.
 *
 * @internal
 *   Plugin classes are internal.
 */
class SourceEditingEnhanced extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface, ContainerFactoryPluginInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * The CKEditor 5 library version checker.
   *
   * @var \Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker
   */
  protected LibraryVersionChecker $libraryVersionChecker;

  /**
   * Constructs a new SourceEditingEnhanced plugin.
   *
   * @param \Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker $libraryVersionChecker
   *   The CKEditor 5 library version checker.
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(
    LibraryVersionChecker $libraryVersionChecker,
    ...$parent_arguments
  ) {
    parent::__construct(...$parent_arguments);
    $this->libraryVersionChecker = $libraryVersionChecker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$parent_arguments): static {
    return new static(
      $container->get('ckeditor5_premium_features.core_library_version_checker'),
      ...$parent_arguments
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Source editor theme'),
      '#default_value' => $this->configuration['theme'] ?? 'default',
      '#options' => [
        'default' => $this->t('Default'),
        'dark' => $this->t('Dark'),
      ],
    ];

    if (!$this->libraryVersionChecker->isLibraryVersionHigherOrEqual('45.0.0')) {
      $form['theme']['#disabled'] = TRUE;
      $form['theme']['#description'] = $this->t('The dark theme is only available in CKEditor 5 version 45.0.0 and higher.');
    }


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['theme'] = $form_state->getValue('theme');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'theme' => 'default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    if ($this->configuration['theme'] !== 'default') {
      $static_plugin_config['sourceEditingEnhanced']['theme'] = $this->configuration['theme'];
    }

    return $static_plugin_config;
  }

}
