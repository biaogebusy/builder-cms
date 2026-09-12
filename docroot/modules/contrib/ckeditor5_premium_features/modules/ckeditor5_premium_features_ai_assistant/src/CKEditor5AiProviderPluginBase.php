<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Base class for ckeditor5_ai_provider plugins.
 */
abstract class CKEditor5AiProviderPluginBase extends PluginBase implements CKEditor5AiProviderInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateFields(FormStateInterface &$form_state): void {
  }

  /**
   * Checks if the required library is installed.
   *
   * Child classes should override this method to provide specific checks.
   *
   * @param string $provider
   *   (optional) The AI provider name.
   *
   * @return bool
   *   TRUE if the required dependencies are installed, FALSE otherwise.
   */
  public static function isInstalled(string $provider = ''): bool {
    return FALSE;
  }

}
