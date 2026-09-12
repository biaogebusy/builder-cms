<?php

namespace Drupal\ace_editor\Plugin\Field\FieldWidget;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'ace_editor' widget.
 *
 * Edits a plain long text field with the Ace editor. Such a field has no text
 * format, so the text editor integration cannot reach it: a formatted field
 * uses the Ace Editor text editor on its format instead.
 *
 * @FieldWidget(
 *   id = "ace_editor",
 *   label = @Translation("Ace Editor"),
 *   field_types = {
 *     "string_long",
 *   },
 * )
 */
class AceWidget extends StringTextareaWidget {

  /**
   * The settings shared with the other integrations, in form order.
   */
  protected const SETTING_KEYS = [
    'theme',
    'syntax',
    'height',
    'width',
    'font_size',
    'line_numbers',
    'print_margins',
    'show_invisibles',
    'use_wrap_mode',
    'auto_complete',
  ];

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, $field_definition, array $settings, array $third_party_settings, ConfigFactoryInterface $config_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('config.factory')
    );
  }

  /**
   * Keeps only the keys that are settings of one editor instance.
   *
   * The option lists of the settings form are not settings: Drupal merges the
   * defaults into the saved display, so carrying them would write both maps
   * into every core.entity_form_display.* configuration (issue #3618752), and
   * would publish 142 syntax options in the markup of every field.
   *
   * @param array $settings
   *   The settings to filter.
   *
   * @return array
   *   The instance settings, in the order the settings form presents them.
   */
  public static function filterSettings(array $settings): array {
    $filtered = [];
    foreach (static::SETTING_KEYS as $key) {
      if (array_key_exists($key, $settings)) {
        $filtered[$key] = $settings[$key];
      }
    }

    return $filtered;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $config = \Drupal::config('ace_editor.settings')->get();

    return static::filterSettings($config) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $settings = $this->getSettings();
    $config = $this->configFactory->get('ace_editor.settings');

    $element['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#options' => $config->get('theme_list'),
      '#default_value' => $settings['theme'] ?? NULL,
    ];
    $element['syntax'] = [
      '#type' => 'select',
      '#title' => $this->t('Syntax'),
      '#description' => $this->t('The language mode used for highlighting.'),
      '#options' => $config->get('syntax_list'),
      '#default_value' => $settings['syntax'] ?? NULL,
    ];
    $element['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('The editor height, in pixels or percent.'),
      '#default_value' => $settings['height'] ?? NULL,
    ];
    $element['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('The editor width, in pixels or percent.'),
      '#default_value' => $settings['width'] ?? NULL,
    ];
    $element['font_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font size'),
      '#default_value' => $settings['font_size'] ?? NULL,
    ];
    $element['line_numbers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show line numbers'),
      '#default_value' => $settings['line_numbers'] ?? FALSE,
    ];
    $element['print_margins'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show print margin'),
      '#default_value' => $settings['print_margins'] ?? FALSE,
    ];
    $element['show_invisibles'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show invisible characters'),
      '#default_value' => $settings['show_invisibles'] ?? FALSE,
    ];
    $element['use_wrap_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Wrap long lines'),
      '#default_value' => $settings['use_wrap_mode'] ?? FALSE,
    ];
    $element['auto_complete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable autocomplete'),
      '#default_value' => $settings['auto_complete'] ?? FALSE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Theme: @theme', ['@theme' => $settings['theme'] ?? '']);
    $summary[] = $this->t('Syntax: @syntax', ['@syntax' => $settings['syntax'] ?? '']);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $settings = static::filterSettings($this->getSettings());

    // Each textarea carries its own settings, so several fields with different
    // themes and syntaxes can be edited on one form.
    $element['value']['#attributes']['class'][] = 'ace-editor-widget';
    $element['value']['#attributes']['data-ace-widget-settings'] = json_encode($settings);

    $libraries = ['ace_editor/widget'];
    if (!empty($settings['theme'])) {
      $libraries[] = 'ace_editor/theme.' . $settings['theme'];
    }
    if (!empty($settings['syntax'])) {
      $libraries[] = 'ace_editor/mode.' . $settings['syntax'];
    }
    $element['value']['#attached']['library'] = $libraries;

    return $element;
  }

}
