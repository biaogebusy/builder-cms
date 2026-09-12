<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the base class for the forms having the shared form structure.
 */
abstract class SharedBuildConfigFormBase extends ConfigFormBase implements SharedBuildConfigFormInterface {

  /**
   * {@inheritdoc}
   */
  abstract public function getFormId(): string;

  /**
   * {@inheritdoc}
   */
  abstract public static function getSettingsRouteName(): string;

  /**
   * {@inheritdoc}
   */
  abstract public function getConfigId(): string;

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      $this->getConfigId(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function form(array $form, FormStateInterface $form_state, Config $config): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config($this->getConfigId());

    return static::form($form, $form_state, $config);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this
      ->config($this->getConfigId())
      ->setData($form_state->cleanValues()->getValues())
      ->save();

    parent::submitForm($form, $form_state);
  }

}
