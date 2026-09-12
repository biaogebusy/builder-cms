<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_indent_block\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Indent Block Plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class IndentBlock extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'enabled' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Indent Block'),
      '#description' => $this->t('
The block indentation feature lets you set indentation for text blocks such as paragraphs or headings.'),
      '#default_value' => $this->configuration['enabled'] ?? FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();
    $this->configuration['enabled'] = isset($values['enabled']) && $values['enabled'];
  }

  /**
   * {@inheritDoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    if (!$this->configuration['enabled']) {
      $static_plugin_config['removePlugins'] = ['IndentBlock'];
      return $static_plugin_config;
    }
    $static_plugin_config['indentBlock']['classes'] = $this->getDefaultClasses();
    return $static_plugin_config;
  }

  /**
   * Returns list of default classes.
   */
  private function getDefaultClasses(): array {
    return [
      'cke5-custom-block-indent-1',
      'cke5-custom-block-indent-2',
      'cke5-custom-block-indent-3',
      'cke5-custom-block-indent-4',
      'cke5-custom-block-indent-5',
      'cke5-custom-block-indent-6',
      'cke5-custom-block-indent-7',
      'cke5-custom-block-indent-8',
      'cke5-custom-block-indent-9',
      'cke5-custom-block-indent-10',
      'cke5-custom-block-indent-11',
      'cke5-custom-block-indent-12',
      'cke5-custom-block-indent-13',
      'cke5-custom-block-indent-14',
      'cke5-custom-block-indent-15',
      'cke5-custom-block-indent-16',
      'cke5-custom-block-indent-17',
      'cke5-custom-block-indent-18',
      'cke5-custom-block-indent-19',
      'cke5-custom-block-indent-20',
    ];
  }

}
