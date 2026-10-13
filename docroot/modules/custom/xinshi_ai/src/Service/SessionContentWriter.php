<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Atomically replaces message content only if the caller read its current version. */
final class SessionContentWriter {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountProxyInterface $account,
    private readonly ResourceTypeRepositoryInterface $resources,
  ) {}

  /** Conflicts return the authorized current content so callers can merge their own change. */
  public function update(string $chat_uuid, string $uuid, string $expected_hash, string $content): array {
    if (!Uuid::isValid($chat_uuid) || !Uuid::isValid($uuid) ||
      !preg_match('/^[a-f0-9]{64}$/D', $expected_hash) || strlen($content) > 4 * 1024 * 1024) {
      throw new UnprocessableEntityHttpException('Invalid session content update.');
    }
    $storage = $this->entities->getStorage('node');
    $transaction = $this->database->startTransaction();
    try {
      // Match ConversationWriter's lock order and reload after locking, never before it.
      $chat_id = $this->lock($chat_uuid, 'conversation');
      $nid = $this->lock($uuid, 'ai_session');
      $storage->resetCache([$chat_id, $nid]);
      $chat = $storage->load($chat_id);
      $node = $storage->load($nid);
      $this->requireOwned($chat, 'conversation');
      $this->requireOwned($node, 'ai_session');
      $sessions = $this->field($chat, 'sessions', ['view']);
      if (!in_array((string) $nid, array_map('strval', array_column($chat->get($sessions)->getValue(), 'target_id')), TRUE)) {
        throw new AccessDeniedHttpException();
      }
      $field = $this->field($node, 'content', ['view', 'edit']);
      $current = (string) $node->get($field)->value;
      $updated = hash_equals(hash('sha256', $current), $expected_hash);
      if ($updated) {
        $node->set($field, $content);
        // Validation and presave retain optional protected-reference and field constraints.
        if (count($node->validate())) {
          throw new UnprocessableEntityHttpException('Invalid session fields.');
        }
        $node->save();
      }
      $result = ['updated' => $updated, 'content' => (string) $node->get($field)->value];
      unset($transaction);
      return $result;
    }
    catch (\Throwable $error) {
      if (isset($transaction)) {
        $transaction->rollBack();
        unset($transaction);
      }
      $storage->resetCache();
      throw $error;
    }
  }

  private function lock(string $uuid, string $bundle): int {
    $nid = $this->database->select('node', 'n')->fields('n', ['nid'])
      ->condition('uuid', $uuid)->condition('type', $bundle)
      ->forUpdate()->execute()->fetchField();
    if (!$nid) {
      throw new NotFoundHttpException();
    }
    return (int) $nid;
  }

  private function requireOwned(?NodeInterface $node, string $bundle): void {
    if (!$node || $node->bundle() !== $bundle || !$this->account->isAuthenticated() ||
      (string) $node->getOwnerId() !== (string) $this->account->id() ||
      !$node->access('view', $this->account) || !$node->access('update', $this->account)) {
      throw new AccessDeniedHttpException();
    }
  }

  private function field(NodeInterface $node, string $public, array $operations): string {
    $resource = $this->resources->get('node', $node->bundle());
    $field = $resource?->getInternalName($public);
    if (!$field || !$resource->isFieldEnabled($field) || !$node->hasField($field)) {
      throw new AccessDeniedHttpException();
    }
    foreach ($operations as $operation) {
      if (!$node->get($field)->access($operation, $this->account)) {
        throw new AccessDeniedHttpException();
      }
    }
    return $field;
  }

}
