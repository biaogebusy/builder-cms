<?php

namespace Drupal\ckeditor_templates\Plugin\CKEditor5Plugin;

use Drupal\Core\Url;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\editor\EditorInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Adds a dialog URL to the CKEditor Plugin.
 */
class CKEditorTemplatesDialog extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['replace_content' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['replace_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace content default value'),
      '#default_value' => $this->configuration['replace_content'] ?? FALSE,
      '#description' => $this->t('Whether the "Replace actual contents" checkbox is checked by default in the Templates dialog.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $form_value = $form_state->getValue('replace_content');
    $form_state->setValue('replace_content', (bool) $form_value);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['replace_content'] = $form_state->getValue('replace_content');
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $dialog_url = Url::fromRoute('ckeditor_templates.selector', [
      'editor' => $editor->id(),
    ])->toString();

    $static_plugin_config['ckeditorTemplates']['dialogUrl'] = $dialog_url;

    return $static_plugin_config;
  }

}
