<?php

declare(strict_types=1);

namespace Drupal\ckeditor5_template\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Template plugin.
 */
class Template extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['file_path'] = [
      '#title' => 'Template file location',
      '#type' => 'textfield',
      '#required' => TRUE,
      '#description' => $this->t('Enter the path to the template file which should be used.'),
      '#default_value' => $config['file_path'] ?? '',
    ];

    $form['show_toolbar_text'] = [
      '#title' => 'Show title in toolbar?',
      '#type' => 'checkbox',
      '#description' => $this->t('Check if you would like to show the title next to the icon in the toolbar.'),
      '#default_value' => $config['show_toolbar_text'] ?? 0,
    ];

    if (!empty($config['show_toolbar_text'])) {
      $form['custom_toolbar_text'] = [
        '#type' => 'textfield',
        '#title' => 'Toolbar label',
        '#description' => $this->t('Enter the text to be displayed next to the toolbar icon. Leave blank for default value.'),
        '#default_value' => $config['custom_toolbar_text'] ?? 'Template',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!file_exists(DRUPAL_ROOT . $form_state->getValue('file_path'))) {
      $form_state->setErrorByName('file_path', 'Template file not found.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['file_path'] = $form_state->getValue('file_path');
    $this->configuration['show_toolbar_text'] = $form_state->getValue('show_toolbar_text');
    if (empty($form_state->getValue('custom_toolbar_text'))){
      $this->configuration['custom_toolbar_text'] = $form_state->setValue('custom_toolbar_text','Template');
    }
    $this->configuration['custom_toolbar_text'] = $form_state->getValue('custom_toolbar_text');
    
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config['template']['file_path'] = $this->configuration['file_path'];
    $static_plugin_config['template']['show_toolbar_text'] = $this->configuration['show_toolbar_text'];
    $static_plugin_config['template']['custom_toolbar_text'] = $this->configuration['custom_toolbar_text'];
    return $static_plugin_config;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'file_path' => '/modules/contrib/ckeditor5_template/template/ckeditor5_template.json.example',
      'custom_toolbar_text' => 'Template',
      'show_toolbar_text' => 1,
    ];
  }

}
