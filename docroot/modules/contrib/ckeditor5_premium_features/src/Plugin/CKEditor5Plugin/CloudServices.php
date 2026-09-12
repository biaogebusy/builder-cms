<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features\CKEditorPremiumPluginsCheckerTrait;
use Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 CloudServices plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class CloudServices extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  use CKEditorPremiumPluginsCheckerTrait;

  /**
   * Creates the cloud service plugin instance.
   *
   * @param \Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface $settingsConfigHandler
   *   The settings configuration handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(
    protected SettingsConfigHandlerInterface $settingsConfigHandler,
    protected ConfigFactoryInterface $configFactory,
    ...$parent_arguments
  ) {
    parent::__construct(...$parent_arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$parent_arguments): static {
    return new static(
      $container->get('ckeditor5_premium_features.config_handler.settings'),
      $container->get('config.factory'),
      ...$parent_arguments
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $config = $this->configFactory->get('ckeditor5_premium_features_realtime_collaboration.settings');
    $filterFormatId = $editor->getFilterFormat()->id();
    if (ckeditor5_premium_features_check_jwt_installed()) {
      $static_plugin_config['cloudServices']['tokenUrl'] = $this->settingsConfigHandler->getTokenUrl($filterFormatId);
    }
    $static_plugin_config['cloudServices']['webSocketUrl'] = $this->settingsConfigHandler->getWebSocketUrl();

    if ($config->get('realtime_permissions')) {
      $bundles = $config->get('editor_bundles') ?? [];
      $bundleVersion = $bundles[$filterFormatId] ?? '';
    }
    else {
      $bundleVersion = $filterFormatId;
    }

    $static_plugin_config['cloudServices']['bundleVersion'] = $bundleVersion;
    $static_plugin_config['comments']['editorConfig']['extraPlugins'] = [];

    return $static_plugin_config;
  }

}
