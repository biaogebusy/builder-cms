<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_productivity_pack\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 Productivity Pack Document Outline Plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class DocumentOutline extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  const CONFIG_FIELD_ENABLED = 'document_outline_enabled';

  /**
   * The id of the plugin in productivity pack.
   */
  const PRODUCTIVITY_PACK_PLUGIN_ID = 'documentOutline';

  /**
   * Creates the plugin instance.
   *
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(...$parent_arguments) {
    parent::__construct(...$parent_arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $settings = $editor->getSettings();
    if (empty($settings['plugins'][ProductivityPackBase::PLUGIN_CONFIG_NAME][self::CONFIG_FIELD_ENABLED])) {
      $static_plugin_config['removePlugins'] = [
        ucfirst($this->getFeaturedPluginId()),
      ];
    }
    return $static_plugin_config;
  }

  /**
   * Gets the featured plugin id.
   *
   * @return string
   *   The CKEditor plugin name.
   */
  public function getFeaturedPluginId(): string {
    return self::PRODUCTIVITY_PACK_PLUGIN_ID;
  }

}
