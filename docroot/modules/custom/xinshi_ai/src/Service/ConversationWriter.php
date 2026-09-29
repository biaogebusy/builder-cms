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

/** Serializes conversation updates and appends message references idempotently. */
final class ConversationWriter {

  private const ATTRIBUTES = ['title', 'chat_type', 'model', 'platform', 'temperature', 'top_p', 'mode', 'ai_optimized', 'sticky'];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountProxyInterface $account,
    private readonly ResourceTypeRepositoryInterface $resources,
  ) {}

  /** Append existing owned sessions and update only explicitly supplied attributes. */
  public function update(string $uuid, array $sessions, array $attributes): array {
    if (!Uuid::isValid($uuid) || !array_is_list($sessions) || count($sessions) > 100 ||
      array_diff(array_keys($attributes), self::ATTRIBUTES)) {
      throw new UnprocessableEntityHttpException('Invalid conversation update.');
    }
    foreach ($sessions as $id) {
      if (!is_string($id) || !Uuid::isValid($id)) {
        throw new UnprocessableEntityHttpException('Invalid session UUID.');
      }
    }
    foreach ($attributes as $value) {
      if (!is_scalar($value)) {
        throw new UnprocessableEntityHttpException('Invalid attribute value.');
      }
    }
    $storage = $this->entities->getStorage('node');
    $resource = $this->resources->get('node', 'conversation');
    if (!$resource) {
      throw new NotFoundHttpException();
    }
    $transaction = $this->database->startTransaction();
    try {
      // Load only after taking the row lock: an entity loaded before it can be stale.
      $nid = $this->database->select('node', 'n')->fields('n', ['nid'])
        ->condition('uuid', $uuid)->condition('type', 'conversation')
        ->forUpdate()->execute()->fetchField();
      if (!$nid) {
        throw new NotFoundHttpException();
      }
      $storage->resetCache([$nid]);
      $node = $storage->load($nid);
      $this->requireOwned($node, 'conversation', 'update');
      $field = $resource->getInternalName('sessions');
      if (!$resource->isFieldEnabled($field) || !$node->hasField($field) ||
        !$node->get($field)->access('edit', $this->account)) {
        throw new AccessDeniedHttpException();
      }
      // Deleted message nodes leave dangling references. Do not carry them into validation.
      $refs = [];
      foreach ($node->get($field)->referencedEntities() as $session) {
        $refs[(string) $session->id()] = ['target_id' => $session->id()];
      }
      foreach (array_unique($sessions) as $id) {
        $found = $storage->loadByProperties(['uuid' => $id, 'type' => 'ai_session']);
        $session = reset($found);
        $this->requireOwned($session ?: NULL, 'ai_session', 'view');
        $refs[(string) $session->id()] = ['target_id' => $session->id()];
      }
      $node->set($field, array_values($refs));
      $optimized_field = $resource->getInternalName('ai_optimized');
      $optimized = (bool) $node->get($optimized_field)->value;
      foreach ($attributes as $public => $value) {
        $internal = $resource->getInternalName($public);
        if (!$resource->isFieldEnabled($internal) || !$node->hasField($internal) ||
          !$node->get($internal)->access('edit', $this->account)) {
          throw new AccessDeniedHttpException();
        }
        // A delayed append/title request must not overwrite an already optimized title.
        if ($optimized && in_array($public, ['title', 'ai_optimized'], TRUE)) {
          continue;
        }
        $node->set($internal, $value);
      }
      if (count($node->validate())) {
        throw new UnprocessableEntityHttpException('Invalid conversation fields.');
      }
      $node->save();
      $result = ['id' => $node->uuid(), 'title' => $node->label(),
        'ai_optimized' => (bool) $node->get($optimized_field)->value];
      // Release the transaction before returning a successful response.
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

  private function requireOwned(?NodeInterface $node, string $bundle, string $operation): void {
    if (!$node || $node->bundle() !== $bundle || !$this->account->isAuthenticated() ||
      (string) $node->getOwnerId() !== (string) $this->account->id() ||
      !$node->access($operation, $this->account)) {
      throw new AccessDeniedHttpException();
    }
  }

}
