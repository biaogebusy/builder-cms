<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Applies an authorized field patch under the shared conversation/message locks. */
final class SessionFieldsWriter {

  private const EDITABLE = ['title', 'content', 'content_format', 'reasoning_content',
    'summary', 'total_tokens', 'img', 'uiparsing', 'style_preset', 'is_error',
    'output_completion', 'ui_review', 'approval_timeline', 'knowledge_refs', 'plan_context'];
  private const JSON_FIELDS = ['ui_review', 'approval_timeline', 'plan_context'];
  private const SAFE_INTEGER = 9007199254740991;

  /** Only the exact locked entity and validated before/after snapshots may pass presave. */
  private readonly \WeakMap $pending;

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountInterface $account,
    private readonly ResourceTypeRepositoryInterface $resources,
    private readonly ChatSessionSnapshot $snapshots,
    private readonly ChatStorage $protocol,
  ) {
    $this->pending = new \WeakMap();
  }

  /** Returns updated, unchanged, or conflict together with an authorized complete snapshot. */
  public function update(string $chat_uuid, string $uuid, int $expected, \stdClass $fields): array {
    $patch = get_object_vars($fields);
    if (!Uuid::isValid($chat_uuid) || !Uuid::isValid($uuid) ||
      $expected < 1 || $expected > self::SAFE_INTEGER || $patch === [] ||
      array_diff(array_keys($patch), self::EDITABLE)) {
      throw new UnprocessableEntityHttpException('Invalid session fields update.');
    }
    $storage = $this->entities->getStorage('node');
    $transaction = $this->database->startTransaction();
    try {
      // Keep the lock order identical to ConversationWriter and SessionContentWriter.
      $chat_id = $this->lock($chat_uuid, 'conversation');
      $nid = $this->lock($uuid, 'ai_session');
      $storage->resetCache([$chat_id, $nid]);
      $chat = $storage->load($chat_id);
      $node = $storage->load($nid);
      $this->requireOwned($chat);
      $this->requireOwned($node);
      $sessions = $this->field($chat, 'sessions', ['view']);
      if (!in_array((string) $nid,
        array_map('strval', array_column($chat->get($sessions)->getValue(), 'target_id')), TRUE)) {
        throw new AccessDeniedHttpException();
      }
      $current = $this->snapshots->validate($node, [$chat_uuid]);
      if ($current->storage_version !== $this->protocol->version()) {
        throw new ConflictHttpException('The message does not match the site storage version.');
      }
      // Conflicts and retries reveal the same DTO, so authorize every returned field first.
      foreach (array_keys(get_object_vars($current)) as $public) {
        $this->field($node, $public, ['view']);
      }
      foreach (array_keys($patch) as $public) {
        $this->field($node, $public, ['edit']);
      }
      $version = $this->field($node, 'edit_version', ['edit']);
      if ($node->getFieldDefinition($version)->isTranslatable()) {
        throw new UnprocessableEntityHttpException('The session requires a shared edit version.');
      }
      foreach ($node->getTranslationLanguages() as $language) {
        $translation = $node->getTranslation($language->getId());
        $this->requireOwned($translation);
        $this->field($translation, 'edit_version', ['edit']);
        foreach (array_keys($patch) as $public) {
          if (!$node->getFieldDefinition($this->snapshots->field($node, $public))->isTranslatable()) {
            $this->field($translation, $public, ['edit']);
          }
        }
      }
      $candidate = clone $current;
      foreach ($patch as $public => $value) {
        $candidate->$public = $value;
      }
      ChatSessionFormat::validate($candidate, $uuid, [$chat_uuid]);
      if (($current->plan_context->legacyPlanRunId ?? NULL) !==
        ($candidate->plan_context->legacyPlanRunId ?? NULL)) {
        throw new UnprocessableEntityHttpException('The historical Plan identity is immutable.');
      }
      if (array_key_exists('content', $patch) && $candidate->content !== NULL) {
        if (ChatSessionSnapshot::managedEnvelope($candidate->content->value)) {
          throw new UnprocessableEntityHttpException('Invalid session fields update.');
        }
        // Text formats are privileges, not arbitrary strings accepted with node update access.
        $format_id = $candidate->content->format ?? filter_fallback_format();
        $format = $this->entities->getStorage('filter_format')->load($format_id);
        if (!$format || !$format->status() || !$format->access('use', $this->account)) {
          throw new AccessDeniedHttpException();
        }
      }
      $same = self::fingerprint($candidate) === self::fingerprint($current);
      // Equality confirms the requested state, not that a particular earlier request committed.
      $status = $expected > $current->edit_version ? 'conflict' :
        ($same ? 'unchanged' : ($expected === $current->edit_version ? 'updated' : 'conflict'));
      if ($status === 'updated') {
        if ($current->edit_version === self::SAFE_INTEGER) {
          throw new UnprocessableEntityHttpException('Session edit version is exhausted.');
        }
        $before = $this->translationFingerprints($node);
        $candidate->edit_version++;
        foreach ($patch as $public => $value) {
          $name = $this->snapshots->field($node, $public);
          $node->set($name, in_array($public, self::JSON_FIELDS, TRUE) && $value !== NULL ?
            ChatSessionFormat::encode($value) : ($value instanceof \stdClass ? (array) $value : $value));
        }
        $node->set($version, $candidate->edit_version);
        if (self::fingerprint($this->snapshots->validate($node, [$chat_uuid])) !==
          self::fingerprint($candidate)) {
          throw new UnprocessableEntityHttpException('Session fields changed during projection.');
        }
        $this->pending[$node] = ['before' => $before, 'after' => $this->translationFingerprints($node)];
        try {
          // Preserve the prior revision for historical reads; the new revision shares the transaction.
          $node->setNewRevision(TRUE);
          $node->setRevisionUserId($this->account->id());
          if (count($node->validate())) {
            throw new UnprocessableEntityHttpException('Invalid session fields.');
          }
          $node->save();
          $storage->resetCache([$nid]);
          $saved = $storage->load($nid);
          if ($this->translationFingerprints($saved) !== $this->pending[$node]['after']) {
            throw new UnprocessableEntityHttpException('Session fields changed during save.');
          }
          $current = $this->snapshots->validate($saved, [$chat_uuid]);
        }
        finally {
          unset($this->pending[$node]);
        }
      }
      unset($transaction);
      return ['status' => $status, 'session' => $current];
    }
    catch (\Throwable $error) {
      if (isset($transaction)) {
        $transaction->rollBack();
        unset($transaction);
      }
      $storage->resetCache();
      if ($error instanceof ChatSessionFormatException) {
        throw new UnprocessableEntityHttpException('Invalid session fields update.');
      }
      throw $error;
    }
  }

  /** Read-only presave check; callers cannot register their own entities or snapshots. */
  public function permits(NodeInterface $node, NodeInterface $original): bool {
    $pending = $this->pending[$node] ?? NULL;
    return $pending !== NULL && $node->id() === $original->id() &&
      $node->uuid() === $original->uuid() &&
      $pending['before'] === $this->translationFingerprints($original) &&
      $pending['after'] === $this->translationFingerprints($node);
  }

  private function translationFingerprints(NodeInterface $node): array {
    $values = [];
    foreach ($node->getTranslationLanguages() as $language) {
      $values[$language->getId()] = self::fingerprint(
        $this->snapshots->validate($node->getTranslation($language->getId())));
    }
    ksort($values);
    return $values;
  }

  private static function fingerprint(mixed $value): string {
    $canonical = static function (mixed $item) use (&$canonical): mixed {
      if ($item instanceof \stdClass) {
        $properties = get_object_vars($item);
        ksort($properties);
        return (object) array_map($canonical, $properties);
      }
      return is_array($item) ? array_map($canonical, $item) : $item;
    };
    return hash('sha256', ChatSessionFormat::encode($canonical($value)));
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

  private function requireOwned(?NodeInterface $node): void {
    if (!$node || !$this->account->isAuthenticated() ||
      (string) $node->getOwnerId() !== (string) $this->account->id() ||
      !$node->access('view', $this->account) || !$node->access('update', $this->account)) {
      throw new AccessDeniedHttpException();
    }
  }

  private function field(NodeInterface $node, string $public, array $operations): string {
    $resource = $this->resources->get('node', $node->bundle());
    $name = $resource?->getInternalName($public);
    if (!$name || !$resource->isFieldEnabled($name) || !$node->hasField($name)) {
      throw new AccessDeniedHttpException();
    }
    foreach ($operations as $operation) {
      if (!$node->get($name)->access($operation, $this->account)) {
        throw new AccessDeniedHttpException();
      }
    }
    return $name;
  }

}
