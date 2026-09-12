<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_font\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Font Size Plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class FontSize extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    if ($this->configuration['options']) {
      [$options] = $this->getParsedOptions($this->configuration['options']);
    }
    if (!empty($options)) {
      $static_plugin_config['fontSize']['options'] = $options;
    }
    return $static_plugin_config;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'options' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['options'] = [
      '#title' => $this->t('Options'),
      '#type' => 'textarea',
      '#description' => $this->t('A list of sizes (in px) that will be provided in the "Font Size" dropdown. Enter one or more values. Note that "default" is controlled by the default styles of the web page.<br /><br />
            <b>Example:</b><br />
            <code>11<br />
                13<br />
                default<br />
                17</code>'),
      '#default_value' => $this->configuration['options'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    [, $wrongValues] = $this->getParsedOptions($form_state->getValue('options'));
    if (!empty($wrongValues)) {
      $form_state->setError($form['options'], 'Unacceptable values provided for the CKEditor 5 Font Size plugin.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    [$options] = $this->getParsedOptions($form_state->getValue('options'));
    $this->configuration['options'] = implode("\n", $options);
  }

  /**
   * Transform the string into an array of options values.
   *
   * @param string|null $options
   *   String to be parsed.
   *
   * @return array
   *   Array of values.
   */
  private function getParsedOptions(?string $options): array {
    $returnOptions = [];
    $wrongValues = [];
    if ($options) {
      $options = explode("\n", $options);
      foreach ($options as $option) {
        $trimmedOption = trim($option);
        if (empty($trimmedOption)) {
          continue;
        }
        if (!is_numeric($trimmedOption) && $trimmedOption !== 'default') {
          $wrongValues[] = $trimmedOption;
        }
        $returnOptions[] = is_numeric($trimmedOption) ? abs($trimmedOption + 0) : $trimmedOption;
      }
    }
    return [$returnOptions, $wrongValues];
  }

}
