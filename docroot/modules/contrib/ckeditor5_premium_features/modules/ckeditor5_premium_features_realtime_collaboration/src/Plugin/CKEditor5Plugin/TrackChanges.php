<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Plugin\CKEditor5Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * CKEditor 5 Track changes plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class TrackChanges extends Realtime {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {

    return [
      'default_state' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $note = $this->t('In order to setup the Real Time Collaboration, use the <a href="@url">global realtime collaboration configuration instead</a>.', [
      '@url' => Url::fromRoute('ckeditor5_premium_features_realtime_collaboration.form.settings')->toString(),
    ]);
    $form['note'] = [
      ['#markup' => '<p>' . $note . '</p>'],
    ];

    $form['default_state'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable on editor init'),
      '#default_value' => $this->configuration['default_state'] ?? FALSE,
      '#description' => t('If checked, track changes will be active by default after editor is initialized.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $formValues = $form_state->getValues();
    $isTurnedOn = $formValues['default_state'] ?? FALSE;
    $this->configuration['default_state'] = (bool) $isTurnedOn;
  }

}
