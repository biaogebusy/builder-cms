<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_text_transformation\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Text transformation Plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class TextTransformation extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'enabled' => FALSE,
      'extra_transformations' => '',
      'extra_regex_transformations' => [],
      'groups' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Text transformation'),
      '#default_value' => $this->configuration['enabled'] ?? FALSE,
      '#attributes' => [
        'data-editor-text-transformation' => 'status',
      ],
    ];

    $form['extra_transformations'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom transformations.'),
      '#description' => $this->t('Add some custom transformations. Enter one or more transformations on each line in the format: from|to. Example: :+1:|👍'),
      '#default_value' => $this->configuration['extra_transformations'],
      '#ajax' => FALSE,
      '#states' => [
        'enable' => [
          ':input[data-editor-text-transformation="status"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-editor-text-transformation="status"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['groups_container'] = [
      '#type' => 'container',
      '#states' => [
        'enable' => [
          ':input[data-editor-text-transformation="status"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-editor-text-transformation="status"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['groups_container']['groups'] = [
      '#type' => 'details',
      '#title' => $this->t('Text transformation groups'),
      '#open' => TRUE,
    ];
    $defaultTransformationsGroups = $this->getDefaultTransformations();
    foreach ($defaultTransformationsGroups as $key => $transformationGroup) {
      $group = [
        '#type' => 'details',
        '#title' => ucfirst($key),
        '#open' => TRUE,
      ];
      $defaultGroupValue = !($key === 'misc');

      $group["enabled"] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable @group_name group', ['@group_name' => ucfirst($key)]),
        '#default_value' => $this->configuration['groups'][$key]['enabled'] ?? $defaultGroupValue,
        '#attributes' => [
          "data-editor-text-transformation_{$key}_enabled" => 'status',
        ],
        '#ajax' => FALSE,
      ];
      $group['line'] = [
        '#type' => 'markup',
        '#markup' => '<hr>',
      ];
      $group['transformations'] = [
        '#type' => 'container',
        '#states' => [
          'enable' => [
            ":input[data-editor-text-transformation_{$key}_enabled=\"status\"]" => ['checked' => TRUE],
          ],
          'visible' => [
            ":input[data-editor-text-transformation_{$key}_enabled=\"status\"]" => ['checked' => TRUE],
          ],
        ],
      ];
      foreach ($transformationGroup as $tkey => $transformation) {
        $group['transformations'][$tkey] = [
          '#type' => 'checkbox',
          '#title' => "<code>" . $tkey . "</code>: " . $transformation,
          '#default_value' => $this->configuration['groups'][$key]['transformations'][$tkey]['enabled'] ?? $defaultGroupValue,
          '#ajax' => FALSE,
        ];
      }
      $form['groups_container']['groups'][$key] = $group;
    }

    $form['regex_transformation_wrapper'] = [
      '#type' => 'details',
      '#states' => [
        'enable' => [
          ':input[data-editor-text-transformation="status"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[data-editor-text-transformation="status"]' => ['checked' => TRUE],
        ],
      ],
      '#title' => $this->t('Advanced settings'),
      '#description' => $this->t('You can define patterns using regular expressions.<br />
                     <b>Note</b>: The pattern must end with `$` and all its fragments must be wrapped
                     with capturing groups.<br />
                     The following rule replaces ` "foo"` with ` «foo»`.<br /><br />
                     expression: (^|\s)(")([^"]*)(")$<br />
                     replace: null,«,null,»
                     '),
      '#open' => $form_state->isRebuilding() ?? FALSE,
      '#id' => 'regex-transformation-wrapper',
    ];

    $regexArr = $this->configuration['extra_regex_transformations'];
    if ($form_state->isRebuilding()) {
      $userInput = $form_state->getUserInput();
      $regexArr = $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_text_transformation__text_transformation']['regex_transformation_wrapper'] ?? [];
    }

    foreach ($regexArr as $regexId => $regex) {
      $form['regex_transformation_wrapper'][$regexId] = [
        '#type' => 'fieldset',
        '#id' => 'regex-container',
      ];
      $form['regex_transformation_wrapper'][$regexId]['from'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Regex pattern'),
        '#placeholder' => '(^|\s)(")([^"]*)(")$',
        '#maxlength' => 255,
        '#default_value' => $regex['from'] ?? '',
      ];
      $form['regex_transformation_wrapper'][$regexId]['to'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Regex replace match'),
        '#placeholder' => 'null,«,null,»',
        '#default_value' => $regex['to'] ?? '',
      ];
      $form['regex_transformation_wrapper'][$regexId]['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'regex-' . $regexId . '-delete',
        '#button_type' => 'danger',
        '#submit' => [[$this, 'removeRegex']],
        '#ajax' => [
          'callback' => [$this, 'refreshRegexCallback'],
          'wrapper' => 'regex-transformation-wrapper',
        ],
        '#attributes' => [
          'data-regex-id' => $regexId,
        ],
      ];
    }
    $form['regex_transformation_wrapper']['add_regex'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Regex'),
      '#submit' => [[$this, 'addRegex']],
      '#ajax' => [
        'callback' => [$this, 'refreshRegexCallback'],
        'wrapper' => 'regex-transformation-wrapper',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $extraTransformations = $form_state->getValue('extra_transformations');
    [, $wrongValues] = $this->getParsedTransformations($extraTransformations);
    if (!empty($wrongValues)) {
      $form_state->setError($form['extra_transformations'],
        $this->t('Unacceptable values provided for the extra transformations: <code>@wrong_values</code>',
        ['@wrong_values' => implode(', ', $wrongValues)]));
    }

    $trigger = $form_state->getTriggeringElement();
    $extraRegexTransformations = $form_state->getValue('regex_transformation_wrapper');
    if (!empty($extraRegexTransformations) && !str_contains($trigger['#id'], 'ckeditor5-plugin-pack-text-transformation-text-transformation-regex-transformation-wrapper')) {
      [, $wrongRegexValues] = $this->getParsedRegexTransformations($extraRegexTransformations);
      if (!empty($wrongRegexValues)) {
        foreach ($wrongRegexValues as $key => $value) {
          $form_state->setError($form['regex_transformation_wrapper'][$key],
            $this->t('Unacceptable values provided for the regex expression: @message', ['@message' => $value]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();
    $this->configuration['enabled'] = isset($values['enabled']) && $values['enabled'];
    foreach ($values['groups_container']['groups'] as $key => $group) {
      $transformations = [];
      foreach ($group['transformations'] as $tkey => $transformation) {
        $transformations[$tkey] = ['enabled' => $transformation];
      }
      $this->configuration['groups'][$key] = [
        'enabled' => $group['enabled'],
        'transformations' => $transformations,
      ];
    }
    $this->configuration['extra_transformations'] = $values['extra_transformations'];
    $this->configuration['extra_regex_transformations'] = $values['regex_transformation_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    if (!$this->configuration['enabled']) {
      $static_plugin_config['removePlugins'] = ['TextTransformation'];
      return $static_plugin_config;
    }

    $transformationsGroups = $this->configuration['groups'];
    $extraTransformations = $this->configuration['extra_transformations'];
    $extraRegexTransformations = $this->configuration['extra_regex_transformations'];

    $enabledTransformations = [];
    foreach ($transformationsGroups as $groupName => $group) {
      if (!$group['enabled'] && $groupName !== 'misc') {
        continue;
      }
      $disabledTransformations = array_filter($group['transformations'], fn($t) => !$t['enabled']);
      if (empty($disabledTransformations) && $groupName !== 'misc') {
        $enabledTransformations[] = $groupName;
        continue;
      }

      $keys = array_keys(array_diff_key($group['transformations'], $disabledTransformations));
      $enabledTransformations = array_merge($enabledTransformations, $keys);
    }

    [$extraValues] = $this->getParsedTransformations($extraTransformations);
    $include = array_merge($enabledTransformations, $extraValues);
    [$regexTransformations] = $this->getParsedRegexTransformations($extraRegexTransformations);

    $static_plugin_config['typing']['transformations']['include'] = $include;
    $static_plugin_config['typing']['transformations']['drupal_config']['regex'] = $regexTransformations;

    return $static_plugin_config;
  }

  /**
   * Returns array of default CKEditor5 text transformations.
   *
   * @return array
   *   Default transformations.
   */
  private function getDefaultTransformations(): array {
    return [
      'typography' => [
        'ellipsis' => 'transforms ... to …',
        'enDash' => 'transforms -- to –',
        'emDash' => 'transforms --- to —',
      ],
      'quotes' => [
        'quotesPrimary' => 'transforms "Foo bar" to “Foo bar”',
        'quotesSecondary' => 'transforms \'Foo bar\' to ‘Foo bar’',
      ],
      'symbols' => [
        'trademark' => 'transforms (tm) to ™',
        'registeredTrademark' => 'transforms (r) to ®',
        'copyright' => 'transforms (c) to ©',
      ],
      'mathematical' => [
        'oneHalf' => 'transforms 1/2 to: ½',
        'oneThird' => 'transforms 1/3 to: ⅓',
        'twoThirds' => 'transforms 2/3 to: ⅔',
        'oneFourth' => 'transforms 1/4 to: ¼',
        'threeQuarters' => 'transforms 3/4 to: ¾',
        'lessThanOrEqual' => 'transforms <= to: ≤',
        'greaterThanOrEqual' => 'transforms >= to: ≥',
        'notEqual' => 'transforms != to: ≠',
        'arrowLeft' => 'transforms <- to: ←',
        'arrowRight' => 'transforms -> to: →',
      ],
      'misc' => [
        'quotesPrimaryEnGb' => 'transforms \'Foo bar\' to ‘Foo bar’',
        'quotesSecondaryEnGb' => 'transforms "Foo bar" to “Foo bar”',
        'quotesPrimaryPl' => 'transforms "Foo bar" to „Foo bar”',
        'quotesSecondaryPl' => 'transforms \'Foo bar\' to ‚Foo bar’',
      ],
    ];
  }

  /**
   * Transform the string into an array of extra transformations .
   *
   * @param string $transformations
   *   String to be parsed.
   *
   * @return array
   *   Array of values.
   */
  private function getParsedTransformations(string $transformations): array {
    $values = explode("\n", $transformations);
    $extraValues = [];
    $wrongValues = [];
    foreach ($values as $value) {
      $trimmedValue = trim($value);
      if (empty($trimmedValue)) {
        continue;
      }
      $transformationValue = explode('|', $trimmedValue);
      if (count($transformationValue) !== 2) {
        $wrongValues[] = $trimmedValue;
      }
      else {
        $extraValues[] = ['from' => $transformationValue[0], 'to' => $transformationValue[1]];
      }
    }
    return [$extraValues, $wrongValues];
  }

  /**
   * Transform array of regexes.
   *
   * @param array $regexTransformations
   *   Array to be parsed.
   *
   * @return array
   *   Array of values.
   */
  private function getParsedRegexTransformations(array $regexTransformations): array {
    $regexValues = [];
    $wrongValues = [];
    foreach ($regexTransformations as $key => $regexTransformation) {
      foreach ($regexTransformation as $rKey => $item) {
        if (empty($item) && !isset($wrongValues[$key])) {
          $wrongValues[$key] = $this->t('Value cannot be empty.');
          continue;
        }
        if ($rKey === 'from' && !str_ends_with($item, '$')) {
          $wrongValues[$key] = $this->t('Pattern must end with $');
        }
        if ($rKey === 'to') {
          $regexTransformation[$rKey] = explode(',', $item);
        }
      }
      if (empty($wrongValues[$key])) {
        $regexValues[] = $regexTransformation;
      }
    }
    return [$regexValues, $wrongValues];
  }

  /**
   * Add regex pattern handler.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function addRegex(array &$form, FormStateInterface $form_state): void {
    $userInput = $form_state->getUserInput();
    $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_text_transformation__text_transformation']['regex_transformation_wrapper'][] = [];
    $form_state->setUserInput($userInput);
    $form_state->setRebuild();
  }

  /**
   * Remove regex pattern handler.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function removeRegex(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $id = $trigger['#attributes']['data-regex-id'];
    $userInput = $form_state->getUserInput();
    $plugin = $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_text_transformation__text_transformation']['regex_transformation_wrapper'];
    if (isset($plugin[$id])) {
      unset($plugin[$id]);
    }
    $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_text_transformation__text_transformation']['regex_transformation_wrapper'] = $plugin;
    $form_state->setUserInput($userInput);

    $form_state->setRebuild();
  }

  /**
   * Refresh regex wrapper callback.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function refreshRegexCallback(array &$form, FormStateInterface $form_state): array {
    $settings_element = $form['editor']['settings']['subform']['plugins']['ckeditor5_plugin_pack_text_transformation__text_transformation'] ?? $form;
    return $settings_element['regex_transformation_wrapper'] ?? $settings_element;
  }

}
