<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_mentions\Form;

use Drupal\ckeditor5_premium_features\Form\SharedBuildConfigFormBase;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the configuration form of the "Mention" feature.
 */
class SettingsForm extends SharedBuildConfigFormBase {
  const MENTION_SETTINGS_ID = 'ckeditor5_premium_features_mentions.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor5_premium_features_mentions_settings';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSettingsRouteName(): string {
    return 'ckeditor5_premium_features_mentions.form.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigId(): string {
    return self::MENTION_SETTINGS_ID;
  }

  /**
   * {@inheritdoc}
   */
  public static function form(array $form, FormStateInterface $form_state, Config $config): array {

    $form['mentions'] = [
      '#type' => 'fieldset',
      '#title' => t('Mentions/Annotations'),
    ];

    $form['mentions']['mention_min_character'] = [
      '#type' => 'number',
      '#title' => t('Minimal mention character.'),
      '#min' => 1,
      '#max' => 8,
      '#default_value' => $config->get('mention_min_character') ?? 1,
      '#description' => t('Set the number of letters after which the autocomplete panel will show up. Allowed values are between 1 and 8.'),
    ];
    $form['mentions']['mention_dropdown_limit'] = [
      '#type' => 'number',
      '#title' => t('Autocomplete list limit.'),
      '#min' => 1,
      '#default_value' => $config->get('mention_dropdown_limit') ?? 4,
      '#description' => t('Set the number of items displayed in the autocomplete list.'),
    ];
    $form['mentions']['mention_marker'] = [
      '#type' => 'textfield',
      '#title' => t('Annotation triggering character.'),
      '#min' => 1,
      '#default_value' => $config->get('mention_marker') ?? '#',
      '#description' => t('Set the character which triggers autocompletion for mentions. It must be a single character.'),
    ];

    $form['mentions']['use_static_permission_condition'] = [
      '#type' => 'checkbox',
      '#title' => t('Use the static permission condition for user query'),
      '#default_value' => $config->get('use_static_permission_condition') ?? FALSE,
      '#description' => t('When enabled, all users with the "to be mentioned" permission will be included in the query.'),
    ];

    return $form;
  }

}
