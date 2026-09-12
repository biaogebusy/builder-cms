<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\Utility;

use Drupal\ckeditor5_premium_features_notifications\Entity\Message;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface;

/**
 * Defines the interface for a bulk message body handler.
 */
interface BulkMessageBodyHandlerInterface {

  /**
   * Prepares body for bulk message.
   *
   * @param \Drupal\ckeditor5_premium_features_notifications\Entity\Message $message
   *   Notification message entity.
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface $messageFactory
   *   Message factory.
   *
   * @return array
   *   The message body.
   */
  public function prepareBody(Message $message, NotificationMessageFactoryInterface $messageFactory): array;

}
