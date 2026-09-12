<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_html_embed\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 HTML embed Plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class HtmlEmbed extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'previews' => FALSE,
      'use_purifier' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {

    $form['previews'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show previews'),
      '#description' => $this->t('Enable previews of embedded HTML content in the editor.'),
      '#default_value' => $this->configuration['previews'] ?? FALSE,
    ];

    $description = $this->t('Enable HTML Purifier to sanitize embedded HTML content in the editor. <br/>
                      This setting won\'t sanitize content saved in the database and displayed on the view page. <br/>');
    if (ckeditor5_plugin_pack_html_embed_is_library_installed()) {
      $description .= $this->t('The DOMPurify library is installed.');
    }
    else {
      $description .= $this->t('The <a href=":link" target="_blank">DOMPurify</a> library <strong>is not installed</strong>. Please install it to be able to use this feature.',
                      [':link' => 'https://github.com/cure53/DOMPurify']);
    }

    $form['use_purifier'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sanitize embedded HTML preview in editor'),
      '#description' => $description,
      '#default_value' => $this->configuration['use_purifier'] ?? FALSE,
      '#states' => [
        'enabled' => [
          ':input[name="editor[settings][plugins][ckeditor5_plugin_pack_html_embed__html_embed][previews]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    if (!ckeditor5_plugin_pack_html_embed_is_library_installed()) {
      $form['use_purifier']['#attributes']['disabled'] = 'disabled';
      unset($form['use_purifier']['#states']['enabled']);
    }

    return $form;
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->cleanValues()->getValues();
    $this->configuration['previews'] = $values['previews'] ?? FALSE;
    $this->configuration['use_purifier'] = $values['use_purifier'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config['htmlEmbed']['showPreviews'] = (bool) $this->configuration['previews'] ?? FALSE;
    $static_plugin_config['htmlEmbed']['enablePurify'] = (bool) $this->configuration['use_purifier'] ?? FALSE;

    return $static_plugin_config;
  }

}
