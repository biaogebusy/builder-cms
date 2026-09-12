<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_notifications\Utility;

use Drupal\ckeditor5_premium_features_notifications\Form\SettingsForm;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Class for accessing notification config values.
 */
class NotificationSettings {

  /**
   * Config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $notificationSettings;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryPluginManager $messageFactoryPluginManager
   *   Message factory plugin manager.
   */
  public function __construct(ConfigFactoryInterface $configFactory,
                              protected NotificationMessageFactoryPluginManager $messageFactoryPluginManager) {
    $this->notificationSettings = $configFactory->get(SettingsForm::NOTIFICATION_CONFIG);
  }

  /**
   * Returns message subject for specified notification type.
   *
   * @param string $messageType
   *   Type of message.
   */
  public function getMessageSubject(string $messageType): string {
    return $this->notificationSettings->get($messageType . '__subject');
  }

  /**
   * Returns message body for specified notification type.
   *
   * @param string $messageType
   *   Type of message.
   */
  public function getMessageBody(string $messageType): string {
    return $this->notificationSettings->get($messageType . '__message')['value'];
  }

  /**
   * Returns TRUE if specified message type is enabled.
   *
   * @param string $messageType
   *   Type of message.
   */
  public function isMessageEnabled(string $messageType): bool {
    return (bool) $this->notificationSettings->get($messageType . '__enabled');
  }

  /**
   * Returns selected message factory plugin ID.
   */
  public function getMessageFactoryPluginId(): string {
    return $this->notificationSettings->get('message_factory_plugin');
  }

  /**
   * Returns selected notification message factory plugin.
   *
   * @return \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface|null
   *   Notification message factory plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getMessageFactoryPlugin(): ?NotificationMessageFactoryInterface {
    $pluginId = $this->getMessageFactoryPluginId();
    if (!$this->messageFactoryPluginManager->hasDefinition($pluginId)) {
      return NULL;
    }

    return $this->messageFactoryPluginManager->createInstance($pluginId);
  }

  /**
   * Returns selected message sender plugin ID.
   */
  public function getSenderPluginId(): string {
    return $this->notificationSettings->get('sender_plugin');
  }

  /**
   * Return the bulk notifications interval setting.
   */
  public function getBulkNotificationsInterval(): int {
    return $this->notificationSettings->get('sender_bulk_interval') ?? 0;
  }

  /**
   * Check if instant comments notifications are set.
   */
  public function areInstantCommentNotificationsSelected(): bool {
    return (bool) $this->notificationSettings->get('instant_comment_notifications') ?? FALSE;
  }

}
