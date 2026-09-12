<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Plugin\Notification;

use Drupal\ckeditor5_premium_features\Event\CollaborationEventBase;
use Drupal\ckeditor5_premium_features\Utility\CollaborationModuleIntegrator;
use Drupal\ckeditor5_premium_features_collaboration\Entity\CommentInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionStorage;
use Drupal\ckeditor5_premium_features_notifications\Utility\NotificationSettings;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcCommentNotificationEntity;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

/**
 * Default class used for notification_messages_factory plugins.
 */
class NotificationMessageFactoryDefault extends PluginBase implements NotificationMessageFactoryInterface, ContainerFactoryPluginInterface {

  use TranslatorTrait;

  /**
   * Suggestion entities storage.
   *
   * @var \Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionStorage|\Drupal\Core\Entity\EntityStorageInterface
   */
  protected SuggestionStorage $suggestionStorage;

  /**
   * Constructor.
   *
   * @param array $configuration
   * @param $pluginId
   * @param $pluginDefinition
   * @param \Drupal\ckeditor5_premium_features_notifications\Utility\NotificationSettings $notificationSettings
   * @param \Drupal\Core\Utility\Token $tokenService
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\ckeditor5_premium_features\Utility\CollaborationModuleIntegrator $collaborationModuleIntegrator
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration,
                              $pluginId,
                              $pluginDefinition,
                              protected NotificationSettings $notificationSettings,
                              protected Token $tokenService,
                              protected EntityTypeManagerInterface $entityTypeManager,
                              protected CollaborationModuleIntegrator $collaborationModuleIntegrator) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);

    if ($collaborationModuleIntegrator->isNonRtcEnabled()) {
      $this->suggestionStorage = $this->entityTypeManager->getStorage(SuggestionInterface::ENTITY_TYPE_ID);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ckeditor5_premium_features_notifications.notification_settings'),
      $container->get('token'),
      $container->get('entity_type.manager'),
      $container->get('ckeditor5_premium_features.collaboration_module_integrator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // The title from YAML file discovery may be a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(string $messageType, CollaborationEventBase $event): NotificationMessageInterface|NULL {
    if (!self::isMessageTypeSupported($messageType)) {
      return NULL;
    }

    try {
      $parameters = $this->getMessageParameters($messageType, $event);
    }
    catch (\Exception) {
      return NULL;
    }

    $subject = $this->tokenService->replace($this->notificationSettings->getMessageSubject($messageType), $parameters);
    $body = $this->tokenService->replace($this->notificationSettings->getMessageBody($messageType), $parameters);

    return new NotificationMessage(
      $messageType,
      $subject,
      $body,
      $event
    );
  }

  /**
   * Returns list of supported message types with their labels.
   */
  public static function getSupportedMessageTypes(): array {
    return [
      self::CKEDITOR5_MESSAGE_DEFAULT => new TranslatableMarkup('Default (any update made)'),
      self::CKEDITOR5_MESSAGE_MENTION_COMMENT => new TranslatableMarkup('Mentioned in a comment'),
      self::CKEDITOR5_MESSAGE_MENTION_DOCUMENT => new TranslatableMarkup('Mentioned in a document'),
      self::CKEDITOR5_MESSAGE_COMMENT_ADDED => new TranslatableMarkup('New comment added'),
      self::CKEDITOR5_MESSAGE_THREAD_REPLY => new TranslatableMarkup('Reply in a thread'),
      self::CKEDITOR5_MESSAGE_SUGGESTION_REPLY => new TranslatableMarkup('Reply to a suggestion'),
      self::CKEDITOR5_MESSAGE_SUGGESTION_STATUS => new TranslatableMarkup('Suggestion status change'),
      self::CKEDITOR5_MESSAGE_SUGGESTION_ADDED => new TranslatableMarkup('New Suggestion added'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function isMessageTypeSupported(string $messageType): bool {
    $supportedTypes = self::getSupportedMessageTypes();
    return isset($supportedTypes[$messageType]);
  }

  /**
   * Returns message parameters.
   *
   * @param string $messageType
   *   Type of message.
   * @param \Drupal\ckeditor5_premium_features\Event\CollaborationEventBase $event
   *   Collaboration event.
   *
   * @return array
   *   List of parameters for a message.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getMessageParameters(string $messageType, CollaborationEventBase $event): array {
    $parameters = [
      'user' => $event->getAccount(),
      'key_id' => $event->getRelatedDocumentFieldId(),
    ];

    $relatedEntity = $event->getRelatedEntity();

    // Set the "document_type" parameter - in most cases the "node".
    if (method_exists($relatedEntity, 'getEntityTypeTargetId')) {
      $parameters[$relatedEntity->getEntityTypeTargetId()] = $relatedEntity->getReferencedEntity();
    }

    $parameters[$relatedEntity->getEntityTypeId()] = $relatedEntity;

    switch ($messageType) {
      case self::CKEDITOR5_MESSAGE_SUGGESTION_REPLY:
        if ($relatedEntity instanceof RtcCommentNotificationEntity) {
          $relatedSuggestion = $relatedEntity->getRelatedSuggestion();
          $parameters[$relatedSuggestion->getEntityTypeId()] = $relatedSuggestion;
        }
        else {
          $relatedSuggestion = $this->suggestionStorage->load($relatedEntity->getThreadId());
          $parameters[$relatedSuggestion->getEntityTypeId()] = $relatedSuggestion;
        }
        break;

      case self::CKEDITOR5_MESSAGE_SUGGESTION_STATUS:
        $parameters['original_content'] = $event->getOriginalContent();
        break;

      case self::CKEDITOR5_MESSAGE_MENTION_COMMENT:
        // Check if mention is in suggestion comment.
        // If so, then set suggestion in $parameters.
        // This will provide valid context in notification.
        if ($relatedEntity instanceof RtcCommentNotificationEntity) {
          $relatedSuggestion = $relatedEntity->getRelatedSuggestion();
          if ($relatedSuggestion) {
            $parameters[$relatedSuggestion->getEntityTypeId()] = $relatedSuggestion;
            break;
          }
        }
        if ($relatedEntity instanceof CommentInterface) {
          $relatedSuggestion = $this->suggestionStorage->load($relatedEntity->getThreadId());
          if ($relatedSuggestion) {
            $parameters[$relatedSuggestion->getEntityTypeId()] = $relatedSuggestion;
            break;
          }
        }
      case self::CKEDITOR5_MESSAGE_MENTION_DOCUMENT:
        $user = User::load($event->getReferencedUserId());
        $parameters['marker'] = $user->getAccountName();
        break;

      case self::CKEDITOR5_MESSAGE_DEFAULT:
        $parameters['is_default'] = TRUE;
        break;
    }

    if ($originalContent = $event->getOriginalContent()) {
      $parameters['original_content'] = $originalContent;
    }
    if ($newContent = $event->getNewContent()) {
      $parameters['new_content'] = $newContent;
    }

    if ($messageType == self::CKEDITOR5_MESSAGE_SUGGESTION_STATUS) {
      $parameters['suggestion'] = $event;
    }

    return $parameters;
  }

}
