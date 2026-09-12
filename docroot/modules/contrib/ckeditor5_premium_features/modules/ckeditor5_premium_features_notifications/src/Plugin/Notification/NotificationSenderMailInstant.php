<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Plugin\Notification;

use Drupal\Core\Database\Connection;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin for sending notifications through mail.
 */
class NotificationSenderMailInstant extends NotificationSenderBase implements ContainerFactoryPluginInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $dbConnection;

  /**
   * Mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailManager;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Database\Connection $dbConnection
   *   Database connection.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   Mail manager service.
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              Connection $dbConnection,
                              MailManagerInterface $mailManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->dbConnection = $dbConnection;
    $this->mailManager = $mailManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('plugin.manager.mail'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationMessageInterface $message, array $userIds): bool {
    $mails = $this->getUserMails($userIds);

    if (empty($mails)) {
      return FALSE;
    }

    $parameters['subject'] = $message->getMessageTitle();
    $parameters['body'] = $message->getMessageBody();

    foreach ($mails as $targetMail) {
      if (!$targetMail) {
        continue;
      }
      $this->mailManager->mail(
        'ckeditor5_premium_features_notifications',
        $message->getType(),
        $targetMail,
        NULL,
        $parameters,
        NULL,
        TRUE
      );
    }

    return TRUE;
  }

  /**
   * Returns a list of user emails.
   *
   * @param array $userIds
   *   List of user IDs.
   *
   * @return array
   *   List of user emails.
   */
  protected function getUserMails(array $userIds): array {
    if (empty($userIds)) {
      return [];
    }

    return $this->dbConnection->select('users_field_data', 'u')
      ->fields('u', ['mail'])
      ->condition('uid', $userIds, 'IN')
      ->execute()
      ->fetchCol();
  }

}
