<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_link_attributes\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Link Attributes.
 *
 * @internal
 *   Plugin classes are internal.
 */
class LinkAttributes extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'attributes' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {

    $form['attributes_wrapper'] = [
      '#type' => 'fieldset',
      '#id' => 'attributes-wrapper',
    ];

    $attributes = $this->configuration['attributes'];
    if ($form_state->isRebuilding()) {
      $userInput = $form_state->getUserInput();
      $attributes = $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_link_attributes__link_attributes']['attributes_wrapper'] ?? [];
    }

    foreach ($attributes as $attributeId => $attribute) {
      $form['attributes_wrapper'][$attributeId] = [
        '#type' => 'fieldset',
        '#id' => 'attributes-container',
      ];
      $form['attributes_wrapper'][$attributeId]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Group label'),
        '#description' => $this->t('Label that will be displayed next to the group switch in the dropdown'),
        '#maxlength' => 255,
        '#default_value' => $attribute['label'] ?? '',
      ];
      $form['attributes_wrapper'][$attributeId]['attributes'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Attributes'),
        '#description' => $this->t('Enter one or more attributes on each line in the format: attribute|value. Example: target|_blank <br /><br />
        When filter "Limit allowed HTML tags and correct faulty HTML" is enabled, make sure the attribute you want to add is present in the allowed list.<br />
        Allowed attributes list: href, target, rel, title, id, class, download, hreflang, type, data-*
        '),
        '#default_value' => $attribute['attributes'] ?? '',
        '#ajax' => FALSE,
      ];

      $form['attributes_wrapper'][$attributeId]['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'attribute-' . $attributeId . '-delete',
        '#button_type' => 'danger',
        '#submit' => [[$this, 'removeAttribute']],
        '#ajax' => [
          'callback' => [$this, 'refreshAttributesCallback'],
          'wrapper' => 'attributes-wrapper',
        ],
        '#attributes' => [
          'data-attribute-id' => $attributeId,
        ],
      ];
    }
    $form['attributes_wrapper']['add_group'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add group'),
      '#submit' => [[$this, 'addAttribute']],
      '#ajax' => [
        'callback' => [$this, 'refreshAttributesCallback'],
        'wrapper' => 'attributes-wrapper',
      ],
    ];

    return $form;
  }

  /**
   * Add new attribute handler.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function addAttribute(array &$form, FormStateInterface $form_state): void {
    $userInput = $form_state->getUserInput();
    $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_link_attributes__link_attributes']['attributes_wrapper'][] = [];
    $form_state->setUserInput($userInput);
    $form_state->setRebuild();
  }

  /**
   * Remove handler.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function removeAttribute(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $id = $trigger['#attributes']['data-attribute-id'];
    $userInput = $form_state->getUserInput();
    $plugin = $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_link_attributes__link_attributes']['attributes_wrapper'];
    if (isset($plugin[$id])) {
      unset($plugin[$id]);
    }
    $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_link_attributes__link_attributes']['attributes_wrapper'] = $plugin;
    $form_state->setUserInput($userInput);

    $form_state->setRebuild();
  }

  /**
   * Refresh attributes wrapper callback.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function refreshAttributesCallback(array &$form, FormStateInterface $form_state): array {
    $settings_element = $form['editor']['settings']['subform']['plugins']['ckeditor5_plugin_pack_link_attributes__link_attributes'] ?? $form;
    return $settings_element['attributes_wrapper'] ?? $settings_element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    if (str_contains($trigger['#id'], 'plugins-ckeditor5-plugin-pack-link-attributes-link-attributes')) {
      return;
    }
    $values = $form_state->getValues();
    $customMarkers = $values['attributes_wrapper'];
    // Remove add button from array.
    unset($customMarkers['add_group']);
    foreach ($customMarkers as $key => $marker) {
      $element = $form['attributes_wrapper'][$key];
      if (empty($marker['label'])) {
        $form_state->setError($element['label'], $this->t('Link attributes: Group label is required.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->cleanValues()->getValues();
    $this->configuration['attributes'] = $values['attributes_wrapper'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $attributes = $this->configuration['attributes'];

    foreach ($attributes as $attribute) {
      $decoratorTitle = $this->convertToCamelCase($attribute['label']);
      $parsedAttributes = $this->getParsedAttributes($attribute['attributes']);

      $static_plugin_config['link']['decorators'][$decoratorTitle] = array_merge([
        'mode' => 'manual',
        'label' => $attribute['label'],
      ], $parsedAttributes);

    }

    return $static_plugin_config;
  }

  /**
   * @param string $input
   *
   * @return string
   */
  private function convertToCamelCase(string $text): string {
    $text = strtolower($text);
    $words = explode(' ', $text);
    $words = array_map(function ($word, $index) {
      return $index === 0 ? $word : ucfirst($word);
    }, $words, array_keys($words));
    return implode('', $words);
  }

  /**
   * @param string $attributes
   *
   * @return array
   */
  private function getParsedAttributes(string $attributes): array {
    $values = explode("\n", $attributes);
    $attributes = [];
    foreach ($values as $value) {
      $trimmedValue = trim($value);
      if (empty($trimmedValue)) {
        continue;
      }
      [$attributeKey, $attributeValue] = explode('|', $trimmedValue);
      if (!$attributeValue) {
        continue;
      }

      if ($attributeKey === 'class') {
        $classes = explode(' ', $attributeValue);
        $attributes['classes'] = isset($attributes['classes']) ? array_merge($attributes['classes'], $classes) : $classes;
      }
      elseif ($attributeKey === 'style') {
        $styles = explode(';', $attributeValue);
        foreach ($styles as $style) {
          $style = trim($style);
          $styleArr = explode(':', $style);
          if (count($styleArr) !== 2) {
            continue;
          }
          $attributes['styles'][$styleArr[0]] = $styleArr[1];
        }
      }
      else {
        $attributes['attributes'][$attributeKey] = $attributeValue;
      }

    }
    if (isset($attributes['classes']) && count($attributes['classes']) == 1) {
      $attributes['classes'] = $attributes['classes'][0];
    }
    return $attributes;
  }

}
