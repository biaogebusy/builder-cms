<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {

  public const CONFIG_NAME = 'ckeditor5_premium_features_ai.settings';

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor5_premium_features_ai_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::CONFIG_NAME);

    $form['service_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('On-Premises Service URL'),
      '#default_value' => $config->get('service_url') ?? '',
      '#description' => $this->t('URL of your self-hosted CKEditor AI server, e.g. https://your-server.example.com (no trailing slash). Leave blank if you are using cloud version of CKEditor AI.'),
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $this->configFactory->getEditable(self::CONFIG_NAME)
      ->set('service_url', trim((string) $form_state->getValue('service_url')))
      ->save();
  }
}
