<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_line_height\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Line Height Plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class LineHeight extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $options = [];
    if ($this->configuration['options']) {
      $options = $this->getParsedOptions($this->configuration['options']);
    }
    foreach ($options as $option) {
      if (!floatval($option) && !in_array($option, ['single', 'double', 'default'])) {
        $static_plugin_config['lineHeight']['options'][] = [
          'title' => $this->t(ucfirst($option))->__toString(),
          'model' => str_replace(' ', '-', $option),
          'view' => [
            'key' => 'class',
            'value' => 'line-height-' . str_replace(' ', '-', $option),
          ],
        ];
      }
      else {
        $static_plugin_config['lineHeight']['options'][] = $option;
      }
    }
    $static_plugin_config['lineHeight']['defaultValue'] = $this->t($this->configuration['default_option']);
    return $static_plugin_config;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'options' => '',
      'default_option' => 'Default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['options'] = [
      '#title' => $this->t('Options'),
      '#type' => 'textarea',
      '#description' => $this->t("A list of options that will be provided in the Line height dropdown. Enter one or more values. The default values are:<br /><br />
            <code>1<br />
              1.15<br />
              default<br />
              2<br />
              2.5<br />
              3<br /></code>
              To learn more about possible options list please refer to the module's <a href=\"https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/ckeditor-5-premium-features/configuration-guide#s-line-height\" target=\"_blank\">configuration guide</a>."),
      '#default_value' => $this->configuration['options'],
    ];

    $form['default_option'] = [
      '#title' => $this->t('Default option label'),
      '#type' => 'textfield',
      '#description' => $this->t('Label of the default option. The default option does not set any style i.e. defaults to the site styling.'),
      '#default_value' => $this->configuration['default_option'],
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
    $options = $this->getParsedOptions($form_state->getValue('options'));
    $this->configuration['options'] = implode("\n", $options);
    $this->configuration['default_option'] = Html::escape($form_state->getValue('default_option'));
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
    $result = [];
    if ($options) {
      $options = explode("\n", $options);
      foreach ($options as $option) {
        $trimmedOption = trim($option);
        if (empty($trimmedOption)) {
          continue;
        }
        $result[] = is_numeric($trimmedOption) ? abs($trimmedOption + 0) : $trimmedOption;
      }
    }
    return $result;
  }

}
