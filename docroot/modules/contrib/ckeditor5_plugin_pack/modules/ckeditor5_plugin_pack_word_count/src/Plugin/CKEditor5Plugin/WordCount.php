<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_plugin_pack_word_count\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Word Count plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class WordCount extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $config = $static_plugin_config;

    if ($this->configuration['word_count_enabled'] === FALSE) {
      $config['removePlugins'] = ['WordCount', 'WordCountAdapter'];
      return $config;
    }

    if ($this->configuration['word_count_mode'] === 'words_only') {
      $config['wordCount']['displayCharacters'] = FALSE;
    }
    elseif ($this->configuration['word_count_mode'] === 'chars_only') {
      $config['wordCount']['displayWords'] = FALSE;
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['word_count_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable the Word Count plugin.'),
      '#default_value' => $this->configuration['word_count_enabled'] ?? FALSE,
    ];

    $form['word_count_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Word Count output.'),
      '#default_value' => $this->configuration['word_count_mode'] ?? 'words_chars',
      '#options' => [
        'words_chars' => $this->t('Show words and characters count.'),
        'words_only' => $this->t('Show words count only.'),
        'chars_only' => $this->t('Show characters count only.'),
      ],
      '#states' => [
        'enabled' => [
          ':input[name="editor[settings][plugins][ckeditor5_plugin_pack_word_count__word_count][word_count_enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->cleanValues()->getValues();
    $values['word_count_enabled'] = isset($values['word_count_enabled']) && $values['word_count_enabled'];
    $this->setConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'word_count_enabled' => FALSE,
      'word_count_mode' => 'words_chars'
    ];
  }

}
