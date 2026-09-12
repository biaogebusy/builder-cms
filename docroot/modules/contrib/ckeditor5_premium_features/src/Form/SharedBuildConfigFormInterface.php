<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the interface for the forms using shared form build.
 */
interface SharedBuildConfigFormInterface {

  /**
   * Provides the shared form build configuration.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   * @param \Drupal\Core\Config\Config $config
   *   The configuration object.
   *
   * @return array
   *   The form structure.
   */
  public static function form(array $form, FormStateInterface $form_state, Config $config): array;

  /**
   * Returns route name for the settings page.
   */
  public static function getSettingsRouteName(): string;

  /**
   * Returns config id.
   */
  public function getConfigId(): string;

}
