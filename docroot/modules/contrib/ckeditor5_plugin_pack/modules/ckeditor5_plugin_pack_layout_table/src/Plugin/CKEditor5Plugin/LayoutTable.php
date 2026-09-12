<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_layout_table\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 Layout Table plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class LayoutTable extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface, CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'use_default_colors' => TRUE,
      'use_hex_colors' => FALSE,
      'colors' => [],
      'default_table_properties' => [
        'border_style' => '',
        'color_picker' => 'hsl',
        'use_default_border_color' => FALSE,
        'border_color' => '',
        'border_width' => '',
        'use_default_bg_color' => FALSE,
        'bg_color' => '',
        'width' => '',
        'height' => '',
        'alignment' => '',
      ],
      'default_table_cell_properties' => [
        'border_style' => '',
        'color_picker' => 'hsl',
        'use_default_border_color' => FALSE,
        'border_color' => '',
        'border_width' => '',
        'use_default_bg_color' => FALSE,
        'bg_color' => '',
        'width' => '',
        'height' => '',
        'padding' => '',
        'text_alignment' => 'left',
        'vertical_alignment' => 'middle',
      ],
      'disable_properties' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['descritpion'] = [
      '#markup' => $this->t('This plugin allows setting default table and cell properties, as well as custom colors for table background and borders. It affects the Table and Layout Table plugins.'),
    ];

    $form['use_default_colors'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use default colors'),
      '#description' => $this->t('CKEditor defines a set of default colors for table properties. Keep it checked to use default colors. You can add custom colors in addition to the default ones. If any custom color type is missing, the default color is used instead.'),
      '#default_value' => $this->configuration['use_default_colors'] ?? TRUE,
    ];

    $form['use_hex_colors'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use hex color codes for default color definitions'),
      '#description' => $this->t('If checked, hex color codes are used for default colors instead of HSL. It’s suggested for text formats that are used to create email templates. Custom colors are always defined as hex color values.'),
      '#default_value' => $this->configuration['use_hex_colors'],
    ];

    $form['default_table_properties'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default Table Properties'),
      '#description' => $this->t('Define default table properties. These properties apply to all tables created with the Layout Table and Table plugins.'),
      '#open' => FALSE,
    ];
    $this->getDefaultPropertiesForm($form, 'default_table_properties');

    $form['default_table_cell_properties'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default Table Cell Properties'),
      '#description' => $this->t('Define default table cell properties. These properties apply to all table cells created with the Layout Table and Table plugins.'),
      '#open' => FALSE,
    ];
    $this->getDefaultPropertiesForm($form, 'default_table_cell_properties');

    $form['custom_colors_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Custom colors'),
      '#id' => 'custom-colors-wrapper',
    ];

    $colors = $this->configuration['colors'] ?? [];
    if ($form_state->isRebuilding()) {
      $userInput = $form_state->getUserInput();
      $colors = $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_layout_table__table_properties']['custom_colors_wrapper'] ?? [];
    }

    foreach ($colors as $colorId => $option) {
      $form['custom_colors_wrapper'][$colorId] = [
        '#type' => 'fieldset',
        '#id' => 'colors-container',
      ];
      $form['custom_colors_wrapper'][$colorId]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Color label'),
        '#maxlength' => 255,
        '#default_value' => $option['label'] ?? '',
      ];
      $form['custom_colors_wrapper'][$colorId]['color'] = [
        '#type' => 'color',
        '#title' => $this->t('Color'),
        '#default_value' => $option['color'] ?? '',
      ];
      $form['custom_colors_wrapper'][$colorId]['color_type'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Type'),
        '#options' => [
          'table_bg' => $this->t('Table Background Color'),
          'table_border' => $this->t('Table Border Color'),
          'table_cell_bg' => $this->t('Table Cell Background Color'),
          'table_cell_border' => $this->t('Table Cell Border Color'),
        ],
        '#default_value' => $option['color_type'] ?? [],
        '#ajax' => FALSE,
      ];
      $form['custom_colors_wrapper'][$colorId]['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'color-' . $colorId . '-delete',
        '#button_type' => 'danger',
        '#submit' => [[$this, 'removeColor']],
        '#ajax' => [
          'callback' => [$this, 'refreshColorsCallback'],
          'wrapper' => 'custom-colors-wrapper',
        ],
        '#attributes' => [
          'data-color-id' => $colorId,
        ],
      ];
    }
    $form['custom_colors_wrapper']['add_custom_marker'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Color'),
      '#submit' => [[$this, 'addCustomColor']],
      '#ajax' => [
        'callback' => [$this, 'refreshColorsCallback'],
        'wrapper' => 'custom-colors-wrapper',
      ],
    ];

    $form['disable_properties'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable Properties'),
      '#default_value' => $this->configuration['disable_properties'],
      '#description' => $this->t('Disables table and table cell properties buttons form the contextual menu. By default they\'re automatically enabled when the "Limit allowed HTML tags and correct faulty HTML" filter is not active. This setting allows to hide those buttons when filter is disabled')
    ];

    return $form;
  }

  private function getDefaultPropertiesForm(array &$form, string $type): void {
    $form[$type]['border_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Default border style'),
      '#description' => $this->t('Define the default table border style.'),
      '#options' => [
        '' => $this->t('None'),
        'solid' => $this->t('Solid'),
        'dotted' => $this->t('Dotted'),
        'dashed' => $this->t('Dashed'),
        'double' => $this->t('Double'),
        'groove' => $this->t('Groove'),
        'ridge' => $this->t('Ridge'),
        'inset' => $this->t('Inset'),
        'outset' => $this->t('Outset'),
      ],
      '#default_value' => $this->configuration[$type]['border_style'] ?? '',
    ];
    $form[$type]['color_picker'] = [
      '#type' => 'select',
      '#title' => $this->t('Color picker format'),
      '#description' => $this->t('Choose the format for the color picker for border and background colors. If you choose "Disabled", the color picker won\'t be available. <br />Hexadecimal or RGB are advised for text formats used to create email templates.'),
      '#options' => [
        'hsl' => $this->t('HSL'),
        'hex' => $this->t('Hexadecimal'),
        'rgb' => $this->t('RGB'),
        'hwb' => $this->t('HWB'),
        'lab' => $this->t('Lab'),
        'lch' => $this->t('LCH'),
        'disabled' => $this->t('Disabled'),
      ],
      '#default_value' => $this->configuration[$type]['color_picker'] ?? 'hsl',
    ];
    $form[$type]['use_default_border_color'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use default border color'),
      '#description' => $this->t('If checked, the border color will be applied to the table.'),
      '#default_value' => $this->configuration[$type]['use_default_border_color'] ?? FALSE,
    ];
    $form[$type]['border_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Default border color'),
      '#description' => $this->t('Define the default table border color.'),
      '#default_value' => $this->configuration[$type]['border_color'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="' . $type . '[use_border_color]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form[$type]['border_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default border width'),
      '#description' => $this->t('Define the default table border width.'),
      '#default_value' => $this->configuration[$type]['border_width'] ?? '',
      '#maxlength' => 10,
    ];
    $form[$type]['use_default_bg_color'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use default background color'),
      '#description' => $this->t('If checked, the background color will be applied to the table.'),
      '#default_value' => $this->configuration[$type]['use_default_bg_color'] ?? FALSE,
    ];
    $form[$type]['bg_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Default background color'),
      '#description' => $this->t('Define the default table background color.'),
      '#default_value' => $this->configuration[$type]['bg_color'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="' . $type . '[use_default_bg_color]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form[$type]['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default width'),
      '#description' => $this->t('Define the default width.'),
      '#default_value' => $this->configuration[$type]['width'] ?? '',
      '#maxlength' => 10,
    ];
    $form[$type]['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default height'),
      '#description' => $this->t('Define the default height.'),
      '#default_value' => $this->configuration[$type]['height'] ?? '',
      '#maxlength' => 10,
    ];

    if ($type == 'default_table_properties') {
      $form[$type]['alignment'] = [
        '#type' => 'select',
        '#title' => $this->t('Default alignment'),
        '#description' => $this->t('Define the default alignment.'),
        '#options' => [
          '' => $this->t('None'),
          'left' => $this->t('Left'),
          'center' => $this->t('Center'),
          'right' => $this->t('right'),
        ],
        '#default_value' => $this->configuration[$type]['alignment'] ?? '',
      ];
    }

    if ($type == 'default_table_cell_properties') {
      $form[$type]['text_alignment'] = [
        '#type' => 'select',
        '#title' => $this->t('Default text alignment'),
        '#description' => $this->t('Define the default text alignment for table cells.'),
        '#options' => [
          'left' => $this->t('Left'),
          'center' => $this->t('Center'),
          'right' => $this->t('Right'),
          'justify' => $this->t('Justify')
        ],
        '#default_value' => $this->configuration[$type]['text_alignment'] ?? 'left',
      ];
      $form[$type]['padding'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Default padding'),
        '#description' => $this->t('Define the default padding for table cells.'),
        '#default_value' => $this->configuration[$type]['padding'] ?? '',
        '#maxlength' => 10,
      ];
      $form[$type]['vertical_alignment'] = [
        '#type' => 'select',
        '#title' => $this->t('Default vertical alignment'),
        '#description' => $this->t('Define the default vertical alignment for table cells.'),
        '#options' => [
          'top' => $this->t('Top'),
          'middle' => $this->t('Middle'),
          'bottom' => $this->t('Bottom'),
        ],
        '#default_value' => $this->configuration[$type]['vertical_alignment'] ?? 'middle',
      ];
    }

  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // No validation needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['use_default_colors'] = (bool)$form_state->getValue('use_default_colors');
    $this->configuration['use_hex_colors'] = (bool)$form_state->getValue('use_hex_colors');
    $colors = $form_state->getValue('custom_colors_wrapper');
    foreach ($colors as $colorId => $color) {
      // Ensure that the color is an array and has the required keys.
      if (!(is_array($color) && isset($color['label'], $color['color'], $color['color_type']))) {
        unset($colors[$colorId]);
      }
      else {
        unset($colors[$colorId]['delete']);
      }
    }
    $this->configuration['colors'] = $colors ?? [];

    $default_table_properties = $form_state->getValue('default_table_properties');
    $this->configuration['default_table_properties'] = [
      'border_style' => $default_table_properties['border_style'],
      'color_picker' => $default_table_properties['color_picker'],
      'use_default_border_color' => (bool)$default_table_properties['use_default_border_color'],
      'border_color' => $default_table_properties['border_color'],
      'border_width' => $default_table_properties['border_width'],
      'use_default_bg_color' => (bool)$default_table_properties['use_default_bg_color'],
      'bg_color' => $default_table_properties['bg_color'],
      'width' => $default_table_properties['width'],
      'height' => $default_table_properties['height'],
      'alignment' => $default_table_properties['alignment'],
    ];

    $default_table_cell_properties = $form_state->getValue('default_table_cell_properties');
    $this->configuration['default_table_cell_properties'] = [
      'border_style' => $default_table_cell_properties['border_style'],
      'color_picker' => $default_table_cell_properties['color_picker'],
      'use_default_border_color' => (bool)$default_table_cell_properties['use_default_border_color'],
      'border_color' => $default_table_cell_properties['border_color'],
      'border_width' => $default_table_cell_properties['border_width'],
      'use_default_bg_color' => (bool)$default_table_cell_properties['use_default_bg_color'],
      'bg_color' => $default_table_cell_properties['bg_color'],
      'width' => $default_table_cell_properties['width'],
      'height' => $default_table_cell_properties['height'],
      'padding' => $default_table_cell_properties['padding'],
      'text_alignment' => $default_table_cell_properties['text_alignment'],
      'vertical_alignment' => $default_table_cell_properties['vertical_alignment'],
    ];

    $this->configuration['disable_properties'] = (bool)$form_state->getValue('disable_properties');
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $default_colors = NULL;

    if (!$this->configuration['use_default_colors']) {
      $default_colors = [];
    }
    elseif ($this->configuration['use_hex_colors']) {
      $default_colors = $this->getDefaultColorsHex();
    }
    if ($default_colors !== NULL) {
      $static_plugin_config['table']['tableProperties']['backgroundColors'] = $default_colors;
      $static_plugin_config['table']['tableProperties']['borderColors'] = $default_colors;
      $static_plugin_config['table']['tableCellProperties']['backgroundColors'] = $default_colors;
      $static_plugin_config['table']['tableCellProperties']['borderColors'] = $default_colors;
    }

    $colors = $this->configuration['colors'] ?? [];
    foreach ($colors as $color) {
      if (!is_array($color)) {
        continue;
      }
      if (is_string($color['color_type']['table_bg'])) {
        $this->addColorToConfig($static_plugin_config, 'tableProperties', 'backgroundColors', $color);
      }
      if (is_string($color['color_type']['table_border'])) {
        $this->addColorToConfig($static_plugin_config, 'tableProperties', 'borderColors', $color);
      }
      if (is_string($color['color_type']['table_cell_bg'])) {
        $this->addColorToConfig($static_plugin_config, 'tableCellProperties', 'backgroundColors', $color);
      }
      if (is_string($color['color_type']['table_cell_border'])) {
        $this->addColorToConfig($static_plugin_config, 'tableCellProperties', 'borderColors', $color);
      }
    }

    $default_table_properties = $this->configuration['default_table_properties'] ?? [];
    $static_plugin_config['table']['contentToolbar'][] = 'tableProperties';
    $static_plugin_config['table']['defaultTableProperties'] = [];
    if (!empty($default_table_properties['border_style'])) {
      $static_plugin_config['table']['defaultTableProperties']['borderStyle'] = $default_table_properties['border_style'];
    }
    if ($default_table_properties['use_default_border_color']) {
      $static_plugin_config['table']['defaultTableProperties']['borderColor'] = $default_table_properties['border_color'];
    }
    if (!empty($default_table_properties['border_width'])) {
      $static_plugin_config['table']['defaultTableProperties']['borderWidth'] = $default_table_properties['border_width'];
    }
    if ($default_table_properties['use_default_bg_color']) {
      $static_plugin_config['table']['defaultTableProperties']['backgroundColor'] = $default_table_properties['bg_color'];
    }
    if (!empty($default_table_properties['width'])) {
      $static_plugin_config['table']['defaultTableProperties']['width'] = $default_table_properties['width'];
    }
    if (!empty($default_table_properties['height'])) {
      $static_plugin_config['table']['defaultTableProperties']['height'] = $default_table_properties['height'];
    }
    if (!empty($default_table_properties['alignment'])) {
      $static_plugin_config['table']['defaultTableProperties']['alignment'] = $default_table_properties['alignment'];
    }
    $color_picker = $default_table_properties['color_picker'] ?? 'hsl';
    if ($color_picker === 'disabled') {
      $static_plugin_config['table']['tableProperties']['colorPicker'] = FALSE;
    }
    else {
      $static_plugin_config['table']['tableProperties']['colorPicker']['format'] = $color_picker;
    }

    $default_table_cell_properties = $this->configuration['default_table_cell_properties'] ?? [];
    $static_plugin_config['table']['contentToolbar'][] = 'tableCellProperties';
    $static_plugin_config['table']['defaultTableCellProperties'] = [];
    if (!empty($default_table_cell_properties['border_style'])) {
      $static_plugin_config['table']['defaultTableCellProperties']['borderStyle'] = $default_table_cell_properties['border_style'];
    }
    if ($default_table_cell_properties['use_default_border_color']) {
      $static_plugin_config['table']['defaultTableCellProperties']['borderColor'] = $default_table_cell_properties['border_color'];
    }
    if (!empty($default_table_cell_properties['border_width'])) {
      $static_plugin_config['table']['defaultTableCellProperties']['borderWidth'] = $default_table_cell_properties['border_width'];
    }
    if ($default_table_cell_properties['use_default_bg_color']) {
      $static_plugin_config['table']['defaultTableCellProperties']['backgroundColor'] = $default_table_cell_properties['bg_color'];
    }
    if (!empty($default_table_cell_properties['width'])) {
      $static_plugin_config['table']['defaultTableCellProperties']['width'] = $default_table_cell_properties['width'];
    }
    if (!empty($default_table_cell_properties['height'])) {
      $static_plugin_config['table']['defaultTableCellProperties']['height'] = $default_table_cell_properties['height'];
    }
    if (!empty($default_table_cell_properties['padding'])) {
      $static_plugin_config['table']['defaultTableCellProperties']['padding'] = $default_table_cell_properties['padding'];
    }
    if (!empty($default_table_cell_properties['text_alignment'])) {
      $static_plugin_config['table']['defaultTableCellProperties']['horizontalAlignment'] = $default_table_cell_properties['text_alignment'];
    }
    if (!empty($default_table_cell_properties['vertical_alignment'])) {
      $static_plugin_config['table']['defaultTableCellProperties']['verticalAlignment'] = $default_table_cell_properties['vertical_alignment'];
    }
    $color_picker = $default_table_cell_properties['color_picker'] ?? 'hsl';
    if ($color_picker === 'disabled') {
      $static_plugin_config['table']['tableCellProperties']['colorPicker'] = FALSE;
    }
    else {
      $static_plugin_config['table']['tableCellProperties']['colorPicker']['format'] = $color_picker;
    }

    $static_plugin_config['table']['disableProperties'] = $this->configuration['disable_properties'] ?? FALSE;

    return $static_plugin_config;
  }

  private function addColorToConfig(array &$config, string $property, string $colorType, array $color): void {
    if (!isset($config['table'][$property][$colorType])) {
      $config['table'][$property][$colorType] = [];
    }

    // Add the color to the configuration.
    $config['table'][$property][$colorType][] = [
      'color' => $color['color'],
      'label' => $color['label'],
    ];
  }

  /**
   * Returns default colors in hexadecimal format.
   *
   * @return array
   *   Array of colors in hexadecimal format.
   */
  private function getDefaultColorsHex(): array {
    return [
      ['color' => '#000000', 'label' => 'Black'],
      ['color' => '#4D4D4D', 'label' => 'Dim grey'],
      ['color' => '#999999', 'label' => 'Grey'],
      ['color' => '#E6E6E6', 'label' => 'Light grey'],
      ['color' => '#FFFFFF', 'label' => 'White'],
      ['color' => '#992626', 'label' => 'Red'],
      ['color' => '#996026', 'label' => 'Orange'],
      ['color' => '#999926', 'label' => 'Yellow'],
      ['color' => '#609926', 'label' => 'Light green'],
      ['color' => '#269926', 'label' => 'Green'],
      ['color' => '#269960', 'label' => 'Aquamarine'],
      ['color' => '#269999', 'label' => 'Turquoise'],
      ['color' => '#266099', 'label' => 'Light blue'],
      ['color' => '#262699', 'label' => 'Blue'],
      ['color' => '#602699', 'label' => 'Purple'],
    ];
  }

  /**
   * Add new color handler.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function addCustomColor(array &$form, FormStateInterface $form_state): void {
    $userInput = $form_state->getUserInput();
    $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_layout_table__table_properties']['custom_colors_wrapper'][] = [];
    $form_state->setUserInput($userInput);
    $form_state->setRebuild();
  }

  /**
   * Remove handler.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function removeColor(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $id = $trigger['#attributes']['data-color-id'];
    $userInput = $form_state->getUserInput();
    $plugin = $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_layout_table__table_properties']['custom_colors_wrapper'];
    if (isset($plugin[$id])) {
      unset($plugin[$id]);
    }
    $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_layout_table__table_properties']['custom_colors_wrapper'] = $plugin;
    $form_state->setUserInput($userInput);

    $form_state->setRebuild();
  }

  /**
   * Refresh colors wrapper callback.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function refreshColorsCallback(array &$form, FormStateInterface $form_state): array {
    $settings_element = $form['editor']['settings']['subform']['plugins']['ckeditor5_plugin_pack_layout_table__table_properties'] ?? $form;
    return $settings_element['custom_colors_wrapper'] ?? $settings_element;
  }
}
