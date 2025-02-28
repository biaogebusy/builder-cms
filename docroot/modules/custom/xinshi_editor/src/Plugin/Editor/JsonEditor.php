<?php

namespace Drupal\xinshi_editor\Plugin\Editor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Plugin\EditorBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines JsonEditor as an Editor plugin.
 *
 * @Editor(
 *   id = "json_editor",
 *   label = "Json Editor",
 *   supports_content_filtering = TRUE,
 *   supports_inline_editing = FALSE,
 *   is_xss_safe = FALSE,
 *   supported_element_types = {
 *     "textarea"
 *   }
 * )
 */
class JsonEditor extends EditorBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultSettings() {
    return [
      'height' => '600px',
      'mode' => 'mode',
      'allow_modes' => [],
    ];
  }

  /**
   * Returns a settings form to configure this text editor.
   *
   * @param array $settings
   *   An array containing form configuration.
   *
   * @return array
   *   A primary render array for the settings form.
   */
  public function getForm(array $settings) {
    return [
      'mode' => [
        '#type' => 'select',
        '#title' => t('Default Mode'),
        '#options' => ['code' => t('Code'), 'form' => t('Form'), 'text' => t('Text'), 'tree' => t('Tree'), 'view' => t('View'), 'preview' => t('Preview')],
        '#attributes' => [
          'style' => 'width: 150px;',
        ],
        '#default_value' => $settings['mode'],
      ],
      'allow_modes' => [
        '#type' => 'checkboxes',
        '#title' => t('Allow Mode'),
        '#options' => ['code' => t('Code'), 'form' => t('Form'), 'text' => t('Text'), 'tree' => t('Tree'), 'view' => t('View'), 'preview' => t('Preview')],
        '#default_value' => array_keys(array_filter($settings['allow_modes'])),
      ],
      'height' => [
        '#type' => 'textfield',
        '#title' => t('Height'),
        '#description' => t('The height of the editor in either pixels or percents.'),
        '#attributes' => [
          'style' => 'width: 100px;',
        ],
        '#default_value' => $settings['height'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $editor = $form_state->get('editor');
    $settings = $editor->getSettings();

    $form = [];

    $form['fieldset'] = [
      '#type' => 'fieldset',
      '#title' => t('Json Editor Settings'),
      '#collapsable' => TRUE,
    ];

    if (array_key_exists('fieldset', $settings)) {
      $form['fieldset'] = array_merge($form['fieldset'], $this->getForm($settings['fieldset']));
    } else {
      $form['fieldset'] = array_merge($form['fieldset'], $this->getForm($settings));
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormValidate(array $form, FormStateInterface $formState) {

  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    // Get default ace_editor configuration.
    return ['xinshi_editor/primary'];
  }

  /**
   * {@inheritdoc}
   */
  public function getJsSettings(Editor $editor) {
    // Pass settings to javascript.
    $settings = $editor->getSettings()['fieldset'];
    $settings['allow_modes'] = array_keys(array_filter($settings['allow_modes']));
    return $settings;
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
    return $form;
  }

}
