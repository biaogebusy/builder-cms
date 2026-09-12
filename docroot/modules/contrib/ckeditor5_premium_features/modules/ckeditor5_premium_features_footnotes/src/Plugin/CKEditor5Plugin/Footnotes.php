<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_footnotes\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Empty Block plugin.
 */
class Footnotes extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {
  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'multi_block' => FALSE,
      'allow_properties' => FALSE,
      'list_style' => 'decimal',
      'start_index' => 1,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['multi_block'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow multi block footnotes'),
      '#default_value' => $this->configuration['multi_block'],
      '#description' => $this->t('If enabled, footnotes can contain multiple blocks such as paragraphs, lists, etc.'),
    ];
    $form['allow_properties'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow change of footnote properties'),
      '#default_value' => $this->configuration['allow_properties'] ?? TRUE,
      '#description' => $this->t('If enabled, users can edit footnote properties such as numbering style and starting index.'),
    ];
    $form['default_properties'] = [
      '#type' => 'details',
      '#title' => $this->t('Default Properties'),
      '#open' => TRUE,
    ];
    $form['default_properties']['list_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Numbering Style'),
      '#options' => [
        'decimal' => $this->t('Decimal (1, 2, 3, ...)'),
        'decimal-leading-zero' => $this->t('Decimal Leading Zero (01, 02, 03, ...)'),
        'lower-roman' => $this->t('Lower Roman (i, ii, iii, ...)'),
        'upper-roman' => $this->t('Upper Roman (I, II, III, ...)'),
        'lower-latin' => $this->t('Lower Latin (a, b, c, ...)'),
        'upper-latin' => $this->t('Upper Latin (A, B, C, ...)'),
      ],
      '#default_value' => $this->configuration['list_style'] ?? 'decimal',
    ];
    $form['default_properties']['start_index'] = [
      '#type' => 'number',
      '#title' => $this->t('Default Starting Index'),
      '#default_value' => $this->configuration['start_index'] ?? 1,
      '#min' => 0,
    ];

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
    $this->configuration['multi_block'] = $form_state->getValue(['multi_block']);
    $this->configuration['allow_properties'] = $form_state->getValue(['allow_properties']);
    $this->configuration['list_style'] = $form_state->getValue(['default_properties', 'list_style']);
    $this->configuration['start_index'] = $form_state->getValue(['default_properties', 'start_index']);
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config['footnotes']['multiBlock'] = $this->configuration['multi_block'];
    $static_plugin_config['footnotes']['footnotesProperties'] = [
      'defaultListStyle' => $this->configuration['list_style'],
      'defaultStartIndex' => (int) $this->configuration['start_index'],
      'toolbar' => [],
    ];
    if ($this->configuration['allow_properties']) {
      $static_plugin_config['footnotes']['footnotesProperties']['toolbar'][] = 'footnotesStyle';
    }

    return $static_plugin_config;
  }
}
