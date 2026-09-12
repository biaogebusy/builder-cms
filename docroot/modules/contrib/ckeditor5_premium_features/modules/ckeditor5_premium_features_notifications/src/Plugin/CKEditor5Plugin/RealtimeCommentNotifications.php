<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features_notifications\Utility\NotificationSettings;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RealtimeCommentNotifications extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * Constructs a plugin instance.
   *
   * @param \Drupal\ckeditor5_premium_features_notifications\Utility\NotificationSettings $notificationSettings
   *   The notifications settings helper.
   *
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(protected NotificationSettings $notificationSettings, ...$parent_arguments) {
    parent::__construct(...$parent_arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$parent_arguments): static {
    return new static(
      $container->get('ckeditor5_premium_features_notifications.notification_settings'),
      ...$parent_arguments
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    if (!$this->notificationSettings->areInstantCommentNotificationsSelected()) {
      $static_plugin_config['removePlugins'] = ['RealtimeCommentNotifications'];
    }

    return $static_plugin_config;
  }


}
