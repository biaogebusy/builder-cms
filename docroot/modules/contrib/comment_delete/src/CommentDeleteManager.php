<?php

namespace Drupal\comment_delete;

use Drupal\comment\CommentInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;

/**
 * Provides comment delete manager.
 */
class CommentDeleteManager implements CommentDeleteManagerInterface {

  use StringTranslationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * The tokens.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The comment storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $commentStorage;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The comment thread manager.
   *
   * @var \Drupal\comment_delete\CommentThreadManagerInterface
   */
  protected CommentThreadManagerInterface $commentThreadManager;

  /**
   * The comment entity.
   *
   * @var \Drupal\comment\CommentInterface
   */
  protected CommentInterface $comment;

  /**
   * The comment delete configuration.
   *
   * @var array
   */
  protected array $config;

  /**
   * CommentDeleteManager constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Utility\Token $token
   *   The tokens.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\comment_delete\CommentThreadManagerInterface $commentThreadManager
   *   The comment thread manager.
   */
  public function __construct(Connection $connection, Token $token, MessengerInterface $messenger, EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, CommentThreadManagerInterface $commentThreadManager) {
    $this->connection = $connection;
    $this->token = $token;
    $this->messenger = $messenger;
    $this->commentStorage = $entityTypeManager->getStorage('comment');
    $this->entityFieldManager = $entityFieldManager;
    $this->commentThreadManager = $commentThreadManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(CommentInterface $comment = NULL): array {
    if (!$comment) {
      $comment = $this->comment;
    }
    $commentedEntity = $comment->getCommentedEntity();
    /** @var \Drupal\field\FieldConfigInterface $fieldDefinition */
    $fieldDefinition = $commentedEntity->getFieldDefinition($comment->getFieldName());

    // Use field widget third party settings when possible.
    $config = [];
    if ($fieldDefinition instanceof ThirdPartySettingsInterface) {
      $config = $fieldDefinition->getThirdPartySettings('comment_delete');
    }
    elseif ($settings = $fieldDefinition->getSetting('third_party_settings')) {
      $config = $settings['comment_delete'] ?? [];
    }

    if ($config) {
      $config['commented_entity'] = $commentedEntity;
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(CommentInterface $comment, string $op): void {
    $this->comment = $comment;
    $this->config = $this->getConfig();

    switch ($op) {
      case 'hard':
        $this->hardDelete();
        break;

      case 'hard_partial':
        $this->moveReplies()->hardDelete(TRUE);
        break;

      case 'soft':
        $this->softDelete();
        break;
    }

    if (trim($this->config['message'][$op])) {
      $this->messenger->addStatus(Markup::create(Xss::filterAdmin(
        $this->token->replace($this->config['message'][$op], ['comment' => $comment])
      )));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function moveReplies(): CommentDeleteManagerInterface {
    // Get the comments immediate replies. Each is reassigned up one thread
    // level or parent comment is entirely removed when at the topmost level.
    if ($ids = $this->commentStorage->getQuery()->accessCheck(FALSE)->condition('pid', $this->comment->id())->execute()) {
      foreach ($this->commentStorage->loadMultiple($ids) as $reply) {
        /** @var \Drupal\comment\CommentInterface $reply */
        if ($this->comment->hasParentComment() && ($pid = $this->comment->getParentComment()->id())) {
          $reply->set('pid', $pid);
        }
        else {
          $reply->set('pid', NULL);
        }
        $reply->save();
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hardDelete(bool $thread = FALSE): CommentDeleteManagerInterface {
    $this->comment->delete();
    if ($thread) {
      $this->commentThreadManager->calculate($this->config['commented_entity']);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function softDelete(): CommentDeleteManagerInterface {
    // Hard delete if the comment has no immediate replies.
    if (!$this->commentStorage->getQuery()->accessCheck(FALSE)->condition('pid', $this->comment->id())->execute()) {
      return $this->hardDelete();
    }

    if ($this->config['mode'] === 'unpublished') {
      $this->comment->setUnpublished();
      $this->comment->save();
    }
    else {
      $baseFields = array_keys($this->entityFieldManager->getBaseFieldDefinitions('comment'));
      $fields = array_diff(array_keys($this->comment->getFields(FALSE)), $baseFields);
      $fields[] = 'subject';

      // Unset all field values.
      foreach ($this->comment->getTranslationLanguages() as $translation) {
        /** @var \Drupal\comment\CommentInterface $entity */
        $entity = $this->comment->getTranslation($translation->getId());
        if ($this->config['anonymize']) {
          $entity->setOwnerId(0);
        }
        foreach ($fields as $field) {
          $entity->set($field, NULL);
        }
        $entity->save();
      }
    }

    return $this;
  }

}
