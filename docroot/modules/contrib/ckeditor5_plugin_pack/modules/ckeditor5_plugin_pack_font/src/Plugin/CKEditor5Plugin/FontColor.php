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
 * CKEditor 5 Font color Plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class FontColor extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'colors' => [],
      'use_default_colors' => TRUE,
      'use_colorpicker' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {

    $form['use_default_colors'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use CKEditor5 default colors'),
      '#description' => $this->t('Default CKEditor5 colors will be available with custom added colors.'),
      '#default_value' => $this->configuration['use_default_colors'] ?? TRUE,
    ];

    $form['use_colorpicker'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use CKEditor5 color picker'),
      '#description' => $this->t('Allow editors to define their own colors'),
      '#default_value' => $this->configuration['use_colorpicker'] ?? TRUE,
    ];

    $form['font_color_columns'] = [
      '#type' => 'number',
      '#title' => $this->t('Font color columns'),
      '#description' => $this->t('Number of columns in the font color grid.'),
      '#default_value' => $this->configuration['font_color_columns'] ?? 5,
    ];
    $form['font_color_document_colors'] = [
      '#type' => 'number',
      '#title' => $this->t('Font color document colors'),
      '#description' => $this->t('Number of document colors displayed in the dropdown. It lists colors that are already used in the document, which might be different from predefined ones in the main section of the dropdown. Set value to 0 to hide the document colors section completely.'),
      '#default_value' => $this->configuration['font_color_document_colors'] ?? 10,
    ];
    $form['bg_color_columns'] = [
      '#type' => 'number',
      '#title' => $this->t('Background color columns'),
      '#description' => $this->t('Number of columns in the background color grid.'),
      '#default_value' => $this->configuration['bg_color_columns'] ?? 5,
    ];
    $form['bg_color_document_colors'] = [
      '#type' => 'number',
      '#title' => $this->t('Background color document colors'),
      '#description' => $this->t('Number of document colors displayed in the dropdown. It lists colors that are already used in the document, which might be different from predefined ones in the main section of the dropdown. Set value to 0 to hide the document colors section completely..'),
      '#default_value' => $this->configuration['bg_color_document_colors'] ?? 10,
    ];
    $form['custom_colors_wrapper'] = [
      '#type' => 'fieldset',
      '#id' => 'custom-colors-wrapper',
    ];

    $colors = $this->configuration['colors'];
    if ($form_state->isRebuilding()) {
      $userInput = $form_state->getUserInput();
      $colors = $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_font__font_color']['custom_colors_wrapper'] ?? [];
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
      $form['custom_colors_wrapper'][$colorId]['type'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Type'),
        '#options' => [
          'font' => $this->t('Font Color'),
          'background' => $this->t('Background Color'),
        ],
        '#default_value' => $option['type'] ?? [],
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
    return $form;
  }

  /**
   * Add new color handler.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function addCustomColor(array &$form, FormStateInterface $form_state): void {
    $userInput = $form_state->getUserInput();
    $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_font__font_color']['custom_colors_wrapper'][] = [];
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
    $plugin = $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_font__font_color']['custom_colors_wrapper'];
    if (isset($plugin[$id])) {
      unset($plugin[$id]);
    }
    $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_font__font_color']['custom_colors_wrapper'] = $plugin;
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
    $settings_element = $form['editor']['settings']['subform']['plugins']['ckeditor5_plugin_pack_font__font_color'] ?? $form;
    return $settings_element['custom_colors_wrapper'] ?? $settings_element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    if (str_contains($trigger['#id'], 'plugins-ckeditor5-plugin-pack-font-font-color-custom-colors-wrapper')) {
      return;
    }
    $values = $form_state->getValues();
    $customColors = $values['custom_colors_wrapper'];
    // Remove add button from array.
    unset($customColors['add_custom_marker']);
    foreach ($customColors as $key => $color) {
      $type = array_filter($color['type'], fn($x) => !empty($x));
      if (empty($type)) {
        $element = $form['custom_colors_wrapper'][$key]['type'];
        $form_state->setError($element, $this->t('Font colors: Color type is required.'));
      }
    }

    if (empty($customColors) && empty($values['use_default_colors'])) {
      $element = $form['use_colorpicker'];
      $form_state->setError($element, $this->t('Use CKEditor5 default colors or add custom colors to disable the color picker'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->cleanValues()->getValues();
    $this->configuration['colors'] = $values['custom_colors_wrapper'] ?? [];
    $this->configuration['use_default_colors'] = (bool) $values['use_default_colors'];
    $this->configuration['use_colorpicker'] = (bool) $values['use_colorpicker'];
    $this->configuration['font_color_columns'] = $values['font_color_columns'];
    $this->configuration['font_color_document_colors'] = $values['font_color_document_colors'];
    $this->configuration['bg_color_columns'] = $values['bg_color_columns'];
    $this->configuration['bg_color_document_colors'] = $values['bg_color_document_colors'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $colors = $this->configuration['colors'];
    $use_colorpicker = $this->configuration['use_colorpicker'];

    $fontColors = array_filter($colors, fn($color) => !empty($color['type']['font']));
    $backgroundColors = array_filter($colors, fn($color) => !empty($color['type']['background']));

    $useDefaultColors = $this->configuration['use_default_colors'];
    if (!empty($colors) && $useDefaultColors) {
      $defaultMarkers = $this->getDefaultColors();
      $fontColors = array_merge($fontColors, $defaultMarkers);
      $backgroundColors = array_merge($backgroundColors, $defaultMarkers);
    }
    // array_values() to make sure that we pass indexed array.
    if (!empty($fontColors)) {
      $static_plugin_config['fontColor']['colors'] = array_values($fontColors);
    }
    if (!empty($backgroundColors)) {
      $static_plugin_config['fontBackgroundColor']['colors'] = array_values($backgroundColors);
    }

    if ($use_colorpicker) {
      $static_plugin_config['fontColor']['colorPicker']['format'] = 'hex';
    }
    else {
      $static_plugin_config['fontColor']['colorPicker'] = FALSE;
      $static_plugin_config['fontBackgroundColor']['colorPicker'] = FALSE;
    }

    $static_plugin_config['fontColor']['columns'] = $this->configuration['font_color_columns'] ?? 5;
    $static_plugin_config['fontColor']['documentColors'] = $this->configuration['font_color_document_colors'] ?? 10;
    $static_plugin_config['fontBackgroundColor']['columns'] = $this->configuration['bg_color_columns'] ?? 5;
    $static_plugin_config['fontBackgroundColor']['documentColors'] = $this->configuration['bg_color_document_colors'] ?? 10;

    return $static_plugin_config;
  }

  /**
   * Returns default values for the Font color plugin.
   *
   * @return array
   *   Array of colors.
   */
  private function getDefaultColors(): array {
    return [
      [
        'color' => 'hsl(0, 0%, 0%)',
        'label' => 'Black',
      ],
      [
        'color' => 'hsl(0, 0%, 30%)',
        'label' => 'Dim grey',
      ],
      [
        'color' => 'hsl(0, 0%, 60%)',
        'label' => 'Grey',
      ],
      [
        'color' => 'hsl(0, 0%, 90%)',
        'label' => 'Light grey',
      ],
      [
        'color' => 'hsl(0, 0%, 100%)',
        'label' => 'White',
        'hasBorder' => TRUE,
      ],
      [
        'color' => 'hsl(0, 75%, 60%)',
        'label' => 'Red',
      ],
      [
        'color' => 'hsl(30, 75%, 60%)',
        'label' => 'Orange',
      ],
      [
        'color' => 'hsl(60, 75%, 60%)',
        'label' => 'Yellow',
      ],
      [
        'color' => 'hsl(90, 75%, 60%)',
        'label' => 'Light green',
      ],
      [
        'color' => 'hsl(120, 75%, 60%)',
        'label' => 'Green',
      ],
      [
        'color' => 'hsl(150, 75%, 60%)',
        'label' => 'Aquamarine',
      ],
      [
        'color' => 'hsl(180, 75%, 60%)',
        'label' => 'Turquoise',
      ],
      [
        'color' => 'hsl(210, 75%, 60%)',
        'label' => 'Light blue',
      ],
      [
        'color' => 'hsl(240, 75%, 60%)',
        'label' => 'Blue',
      ],
      [
        'color' => 'hsl(270, 75%, 60%)',
        'label' => 'Purple',
      ],
    ];
  }

}
