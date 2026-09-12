<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 Drupal powered by plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class DrupalPoweredBy extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(private ?SettingsConfigHandlerInterface $premiumFeaturesConfig, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->has('ckeditor5_premium_features.config_handler.settings') ? $container->get('ckeditor5_premium_features.config_handler.settings') : NULL,
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    if (!$this->premiumFeaturesConfig?->getLicenseKey()) {
      $static_plugin_config['drupalPoweredBy'] = TRUE;
    }
    return $static_plugin_config;
  }

}
