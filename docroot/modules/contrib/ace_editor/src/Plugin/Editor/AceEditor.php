<?php

namespace Drupal\ace_editor\Plugin\Editor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Plugin\EditorBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines AceEditor as an Editor plugin.
 *
 * @Editor(
 *   id = "ace_editor",
 *   label = "Ace Editor",
 *   supports_content_filtering = TRUE,
 *   supports_inline_editing = FALSE,
 *   is_xss_safe = FALSE,
 *   supported_element_types = {
 *     "textarea"
 *   }
 * )
 */
class AceEditor extends EditorBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultSettings() {
    $config = \Drupal::config('ace_editor.settings')->get();
    return $config;
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

    $config = \Drupal::config('ace_editor.settings');

    return [
      'theme' => [
        '#type' => 'select',
        '#title' => $this->t('Theme'),
        '#options' => $config->get('theme_list'),
        '#attributes' => [
          'style' => 'width: 150px;',
        ],
        '#default_value' => $settings['theme'],
      ],
      'syntax' => [
        '#type' => 'select',
        '#title' => $this->t('Syntax'),
        '#description' => $this->t('The syntax that will be highlighted.'),
        '#options' => $config->get('syntax_list'),
        '#attributes' => [
          'style' => 'width: 150px;',
        ],
        '#default_value' => $settings['syntax'],
      ],
      'height' => [
        '#type' => 'textfield',
        '#title' => $this->t('Height'),
        '#description' => $this->t('The height of the editor in either pixels or percents.'),
        '#attributes' => [
          'style' => 'width: 100px;',
        ],
        '#default_value' => $settings['height'],
      ],
      'width' => [
        '#type' => 'textfield',
        '#title' => $this->t('Width'),
        '#description' => $this->t('The width of the editor in either pixels or percents.'),
        '#attributes' => [
          'style' => 'width: 100px;',
        ],
        '#default_value' => $settings['width'],
      ],
      'font_size' => [
        '#type' => 'textfield',
        '#title' => $this->t('Font size'),
        '#description' => $this->t('The font size used in the editor.'),
        '#attributes' => [
          'style' => 'width: 100px;',
        ],
        '#default_value' => $settings['font_size'],
      ],
      'line_numbers' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Show line numbers'),
        '#default_value' => $settings['line_numbers'],
      ],
      'print_margins' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Show print margin (80 chars)'),
        '#default_value' => $settings['print_margins'],
      ],
      'show_invisibles' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Show invisible characters (whitespaces, EOL...)'),
        '#default_value' => $settings['show_invisibles'],
      ],
      'use_wrap_mode' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Toggle word wrapping'),
        '#default_value' => $settings['use_wrap_mode'],
      ],
      'auto_complete' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Autocomplete (Ctrl+Space'),
        '#default_value' => isset($settings['auto_complete']) ? $settings['auto_complete'] : TRUE,
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
      '#title' => $this->t('Ace Editor Settings'),
      '#collapsable' => TRUE,
    ];

    if (array_key_exists('fieldset', $settings)) {
      $form['fieldset'] = array_merge($form['fieldset'], $this->getForm($settings['fieldset']));
    }
    else {
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
    $config = \Drupal::config('ace_editor.settings');

    // Get theme and mode.
    $theme = trim($editor->getSettings()['fieldset']['theme']);
    $mode = trim($editor->getSettings()['fieldset']['syntax']);

    // Check if theme and mode library exist.
    $theme_exist = \Drupal::service('library.discovery')->getLibraryByName('ace_editor', 'theme.' . $theme);
    $mode_exist = \Drupal::service('library.discovery')->getLibraryByName('ace_editor', 'mode.' . $mode);

    // ace_editor/primary the basic library for ace_editor.
    $libs = ['ace_editor/primary'];

    if ($theme_exist) {
      $libs[] = 'ace_editor/theme.' . $theme;
    }
    else {
      $libs[] = 'ace_editor/theme.' . $config->get('theme');
    }

    if ($mode_exist) {
      $libs[] = 'ace_editor/mode.' . $mode;
    }
    else {
      $libs[] = 'ace_editor/mode.' . $config->get('syntax');
    }

    return $libs;
  }

  /**
   * {@inheritdoc}
   */
  public function getJsSettings(Editor $editor) {
    // Pass settings to javascript.
    return $editor->getSettings()['fieldset'];
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
