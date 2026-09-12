<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_productivity_pack\Form\ContentTemplates;

use Drupal\ckeditor5_premium_features\Form\SharedBuildConfigFormBase;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the configuration form of the "Export to PDF" feature.
 */
class SettingsForm extends SharedBuildConfigFormBase {

  const CONTENT_TEMPLATES_CONFIG_NAME = 'ckeditor5_premium_features_productivity_pack_content_templates.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor5_premium_features_productivity_pack_content_templates_settings';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSettingsRouteName(): string {
    return 'ckeditor5_premium_features_productivity_pack_content_templates.form.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigId(): string {
    return self::CONTENT_TEMPLATES_CONFIG_NAME;
  }

  /**
   * {@inheritdoc}
   */
  public static function form(array $form, FormStateInterface $form_state, Config $config): array {
    $form['definitions'] = [
      '#type' => 'textarea',
      '#title' => t('Template definitions'),
      '#description' => t('Provide array of JSON objects with template definitions.'),
      '#default_value' => $config->get('definitions'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $definitions = $form_state->getValue('definitions');
    if ($definitions && !json_decode($definitions)) {
      $form_state->setErrorByName('definitions', "Invalid JSON format");
    }
    parent::validateForm($form, $form_state);
  }

}
