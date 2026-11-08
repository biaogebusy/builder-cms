<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\Uuid;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai_reference\ChatHistoryReader;
use Drupal\xinshi_ai_reference\ChatSessionFormat;
use Drupal\xinshi_ai_reference\ChatSessionFormatException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Publishes an explicit HTML snapshot without exposing conversation storage or provenance. */
final class PublishedPage {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountInterface $account,
    private readonly ChatHistoryReader $history,
    private readonly UuidInterface $uuid,
    private readonly TimeInterface $time,
  ) {}

  /** Read only publication metadata after checking the author and source membership. */
  public function status(string $chat_uuid, string $uuid): array {
    $this->source($chat_uuid, $uuid);
    return $this->metadata($chat_uuid, $uuid);
  }

  /** Atomically replace the public snapshot only for the exact author-selected source version. */
  public function publish(string $chat_uuid, string $uuid, string $expected): array {
    $transaction = $this->database->startTransaction();
    try {
      $fields = $this->source($chat_uuid, $uuid, TRUE);
      if ($fields->storage_mode !== 'inline' || $fields->session_role !== 'assistant' ||
        $fields->is_error || !is_string($fields->content->value ?? NULL)) {
        throw new UnprocessableEntityHttpException('Only a saved HTML assistant result can be published.');
      }
      $html = $this->html($fields->content->value);
      if (!hash_equals(hash('sha256', $html), $expected)) {
        throw new ConflictHttpException('The source changed; reload before publishing.');
      }
      $binding = $this->database->select('xinshi_chat_published_page', 'p')
        ->fields('p', ['conversation_uuid'])->condition('session_uuid', $uuid)->execute()->fetchField();
      if ($binding !== FALSE && $binding !== $chat_uuid) {
        throw new ConflictHttpException('Unpublish the page in its original conversation first.');
      }
      $current = $this->metadata($chat_uuid, $uuid);
      $id = $current['page']['id'] ?? $this->uuid->generate();
      $this->database->merge('xinshi_chat_published_page')->key('session_uuid', $uuid)->fields([
        'id' => $id, 'conversation_uuid' => $chat_uuid, 'uid' => $this->account->id(),
        'html' => $html, 'html_hash' => $expected, 'edit_version' => $fields->edit_version,
        'updated' => $this->time->getCurrentTime(),
      ])->execute();
      $result = $this->metadata($chat_uuid, $uuid);
      unset($transaction);
      return $result;
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

  /** Revocation removes the public identifier; a later publication receives a new identifier. */
  public function revoke(string $chat_uuid, string $uuid): void {
    $transaction = $this->database->startTransaction();
    try {
      $this->source($chat_uuid, $uuid, TRUE);
      $this->database->delete('xinshi_chat_published_page')->condition('session_uuid', $uuid)
        ->condition('uid', $this->account->id())->execute();
      unset($transaction);
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

  /** The public DTO contains HTML only; source IDs and private message fields never leave storage. */
  public function read(string $id): ?array {
    if (!Uuid::isValid($id)) {
      return NULL;
    }
    $query = $this->database->select('xinshi_chat_published_page', 'p');
    $query->innerJoin('users_field_data', 'u', 'u.uid = p.uid');
    $html = $query->fields('p', ['html'])->condition('p.id', $id)->condition('u.status', 1)
      ->execute()->fetchField();
    return $html === FALSE ? NULL : ['html' => $html];
  }

  /** Deleting either source removes its publications in the entity deletion transaction. */
  public function removeSource(NodeInterface $node): void {
    $field = match ($node->bundle()) {
      'conversation' => 'conversation_uuid', 'ai_session' => 'session_uuid', default => NULL,
    };
    if ($field !== NULL) {
      $this->database->delete('xinshi_chat_published_page')->condition($field, $node->uuid())->execute();
    }
  }

  private function source(string $chat_uuid, string $uuid, bool $lock = FALSE): \stdClass {
    if (!$this->account->isAuthenticated() || !Uuid::isValid($chat_uuid) || !Uuid::isValid($uuid)) {
      throw new AccessDeniedHttpException();
    }
    $storage = $this->entities->getStorage('node');
    // Match the existing conversation-before-message lock order used by CAS and deletion.
    foreach (['conversation' => $chat_uuid, 'ai_session' => $uuid] as $bundle => $source_uuid) {
      $query = $this->database->select('node', 'n')->fields('n', ['nid'])
        ->condition('uuid', $source_uuid)->condition('type', $bundle);
      if ($lock) {
        $query->forUpdate();
      }
      $nid = $query->execute()->fetchField();
      if (!$nid) {
        throw new NotFoundHttpException();
      }
      $storage->resetCache([$nid]);
      $node = $storage->load($nid);
      if (!$node || (string) $node->getOwnerId() !== (string) $this->account->id() ||
        !$node->access('view', $this->account) || !$node->access('update', $this->account)) {
        throw new AccessDeniedHttpException();
      }
    }
    // This existing reader rechecks membership, the selected storage version and every returned field.
    return $this->history->session($chat_uuid, $uuid);
  }

  private function metadata(string $chat_uuid, string $uuid): array {
    $row = $this->database->select('xinshi_chat_published_page', 'p')
      ->fields('p', ['id', 'edit_version', 'html_hash', 'updated'])->condition('conversation_uuid', $chat_uuid)
      ->condition('session_uuid', $uuid)->condition('uid', $this->account->id())->execute()->fetchAssoc();
    return ['page' => $row ? ['id' => $row['id'], 'editVersion' => (int) $row['edit_version'],
      'htmlHash' => $row['html_hash'], 'updated' => (int) $row['updated']] : NULL];
  }

  private function html(string $content): string {
    $json = trim($content);
    if (preg_match('/\A```(?:json)?\s*([\s\S]*?)\s*```\z/', $json, $match)) {
      $json = $match[1];
    }
    try {
      $data = ChatSessionFormat::decode($json);
    }
    catch (ChatSessionFormatException) {
      throw new UnprocessableEntityHttpException('Invalid saved HTML result.');
    }
    $widget = $data instanceof \stdClass ? ($data->widget ?? NULL) : NULL;
    if (!$widget instanceof \stdClass || ($widget->type ?? NULL) !== 'full-html' ||
      !is_string($widget->html ?? NULL) || trim($widget->html) === '' ||
      strlen($widget->html) > ChatSessionFormat::CONTENT_BYTES) {
      throw new UnprocessableEntityHttpException('Only a saved FullHTML result can be published.');
    }
    return $widget->html;
  }

}
