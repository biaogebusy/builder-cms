<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Plugin\Notification;

/**
 * Interface for notification_sender plugins.
 */
interface NotificationSenderInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label();

  /**
   * Sends notifications message to specified users.
   *
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageInterface $message
   *   Mssage to be sent.
   * @param array $userIds
   *   List of recipients.
   *
   * @return bool
   *   Notification sending result. FALSE if nothing ws send.
   */
  public function send(NotificationMessageInterface $message, array $userIds): bool;

}
