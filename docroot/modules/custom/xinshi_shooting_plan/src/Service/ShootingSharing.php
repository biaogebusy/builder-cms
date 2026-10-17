<?php

declare(strict_types=1);

namespace Drupal\xinshi_shooting_plan\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Explicit sharing delegates only the saved working copy, never task or account authority. */
final class ShootingSharing {

  public const TABLE = 'xinshi_shooting_share';

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountProxyInterface $account,
    private readonly AccountSwitcherInterface $switcher,
    private readonly ShootingDocumentAccess $documents,
    private readonly ShootingSketches $sketches,
  ) {}

  /** Only an authorized author can inspect or change the sharing settings. */
  public function settings(string $uuid): array {
    $node = $this->documents->ownerDocument($uuid, TRUE);
    $row = $this->row($uuid);
    if (($row['owner_uid'] ?? NULL) != $node->getOwnerId()) {
      $row = [];
    }
    $editor = empty($row['editor_uid']) ? NULL : $this->entities->getStorage('user')->load($row['editor_uid']);
    return ['token' => $row['token'] ?? NULL,
      'editor' => $editor ? ['id' => (int) $editor->id(), 'name' => $editor->getAccountName()] : NULL];
  }

  /** Disabling a link retires its token; re-enabling never restores an old public URL. */
  public function configure(string $uuid, array $input): array {
    if (array_diff(array_keys($input), ['enabled', 'editorId']) ||
      !is_bool($input['enabled'] ?? NULL) || !is_int($input['editorId'] ?? NULL) || $input['editorId'] < 0) {
      throw new UnprocessableEntityHttpException('shooting_sharing_invalid');
    }
    $transaction = $this->database->startTransaction();
    try {
      // Settings and collaborative writes take the same plan lock before checking the grant.
      $node = $this->documents->ownerDocument($uuid, TRUE);
      $doc = ShootingDocumentAccess::document((string) $node->get('field_shooting_document')->value, $uuid);
      if ($input['enabled'] && $doc['status'] !== 'ready') {
        throw new UnprocessableEntityHttpException('shooting_document_not_ready');
      }
      if ($input['editorId']) {
        $editor = $this->entities->getStorage('user')->load($input['editorId']);
        if (!$editor || $editor->isBlocked() || (string) $editor->id() === (string) $node->getOwnerId()) {
          throw new UnprocessableEntityHttpException('shooting_editor_invalid');
        }
      }
      $row = $this->row($uuid);
      if (($row['owner_uid'] ?? NULL) != $node->getOwnerId()) {
        $row = [];
      }
      $this->database->merge(self::TABLE)->key('plan_uuid', $uuid)->fields([
        'owner_uid' => (int) $node->getOwnerId(),
        'token' => $input['enabled'] ? ($row['token'] ?? bin2hex(random_bytes(32))) : NULL,
        'editor_uid' => $input['editorId'],
      ])->execute();
      unset($transaction);
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
    return $this->settings($uuid);
  }

  /** Bounded account-name lookup for an author choosing a collaborator; no emails or roles. */
  public function users(string $uuid, string $query): array {
    $this->documents->ownerDocument($uuid, TRUE);
    $query = trim($query);
    if (mb_strlen($query) < 2 || mb_strlen($query) > 80) {
      return [];
    }
    $storage = $this->entities->getStorage('user');
    // The owner's plan grant permits this fixed, minimal account-picker projection.
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('status', 1)
      ->condition('uid', 0, '>')->condition('uid', $this->account->id(), '<>')
      ->condition('name', $query, 'CONTAINS')->sort('name')->range(0, 20)->execute();
    return array_values(array_map(static fn($user) => ['id' => (int) $user->id(),
      'name' => $user->getAccountName()], $storage->loadMultiple($ids)));
  }

  /** Public projection omits task identities, original output, references and other account data. */
  public function read(string $token): array {
    [$node, $row] = $this->source($token);
    $content = (string) $node->get('field_shooting_document')->value;
    $doc = ShootingDocumentAccess::document($content, $node->uuid());
    $editable = $this->canEdit($node, $row);
    $sketches = $this->asOwner($node, fn() => $this->sketches->read($node->uuid()));
    $public_prompts = ShootingDocumentAccess::prompts($doc['draft']);
    foreach ($sketches as $id => &$sketch) {
      $sketch = ['status' => $sketch['status'], 'prompt' => $public_prompts[$id], 'retryable' => FALSE, 'stale' => $sketch['stale']] +
        (!empty($sketch['src']) ? ['src' => '/api/v3/ai/shooting-plans/shared/' . $token . '/sketches/' . rawurlencode((string) $id)] : []);
    }
    return ['id' => $node->uuid(), 'request' => $doc['request'], 'plan' => $doc['draft'],
      'canEdit' => $editable, 'contentHash' => $editable ? hash('sha256', $content) : NULL,
      'sketches' => (object) $sketches];
  }

  /** A collaborator can replace only the draft, with the same lock and CAS as owner saves. */
  public function update(string $token, array $input): array {
    if (array_diff(array_keys($input), ['expectedHash', 'plan']) ||
      !is_string($input['expectedHash'] ?? NULL) || !preg_match('/^[a-f0-9]{64}$/D', $input['expectedHash'])) {
      throw new UnprocessableEntityHttpException('shooting_update_invalid');
    }
    ShootingSharedDraft::validate($input['plan'] ?? NULL);
    $transaction = $this->database->startTransaction();
    try {
      [$node, $row] = $this->source($token, TRUE);
      if (!$this->canEdit($node, $row)) {
        throw new AccessDeniedHttpException();
      }
      $current = (string) $node->get('field_shooting_document')->value;
      $doc = ShootingDocumentAccess::document($current, $node->uuid());
      if (!hash_equals(hash('sha256', $current), $input['expectedHash'])) {
        // Repeating an acknowledged draft is safe after a lost response, but never overwrites it.
        if ($doc['draft'] !== $input['plan']) {
          throw new ConflictHttpException('shooting_document_changed');
        }
      }
      else {
        $doc['draft'] = $input['plan'];
        $node->set('field_shooting_document', json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if (count($node->validate())) {
          throw new UnprocessableEntityHttpException('shooting_document_invalid');
        }
        $node->save();
      }
      $result = ['contentHash' => hash('sha256', (string) $node->get('field_shooting_document')->value)];
      unset($transaction);
      return $result;
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      $this->entities->getStorage('node')->resetCache();
      throw $error;
    }
  }

  /** Each image request rechecks the link and all owner source/file permissions. */
  public function image(string $token, string $shot_id): \Drupal\file\FileInterface {
    [$node] = $this->source($token);
    $file = $this->asOwner($node, fn() => $this->sketches->image($node->uuid(), $shot_id));
    if (!$file || !in_array($file->getMimeType(), ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], TRUE)) {
      throw new NotFoundHttpException();
    }
    return $file;
  }

  private function source(string $token, bool $write = FALSE): array {
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
      throw new NotFoundHttpException();
    }
    $row = $this->database->select(self::TABLE, 's')->fields('s')->condition('token', $token)->execute()->fetchAssoc();
    if (!$row) {
      throw new NotFoundHttpException();
    }
    $storage = $this->entities->getStorage('node');
    // A token lookup is an explicit grant, not a generic entity or task access bypass.
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'shooting_plan')
      ->condition('uuid', $row['plan_uuid'])->range(0, 1)->execute();
    $storage->resetCache($ids);
    $node = $ids ? $storage->load(reset($ids)) : NULL;
    if (!$node || (string) $node->getOwnerId() !== (string) $row['owner_uid']) {
      throw new NotFoundHttpException();
    }
    $node = $this->asOwner($node, fn() => $this->documents->ownerDocument($row['plan_uuid'], $write));
    // Re-read after obtaining the plan lock: a concurrent revocation must win before a write.
    $row = $this->row($node->uuid());
    if (empty($row['token']) || !hash_equals($row['token'], $token)) {
      throw new NotFoundHttpException();
    }
    $doc = ShootingDocumentAccess::document((string) $node->get('field_shooting_document')->value, $node->uuid());
    if ($doc['status'] !== 'ready') {
      throw new NotFoundHttpException();
    }
    return [$node, $row];
  }

  private function canEdit(NodeInterface $node, array $row): bool {
    $viewer = $this->account->getAccount();
    if (!$viewer->isAuthenticated() || !in_array((string) $viewer->id(),
      [(string) $node->getOwnerId(), (string) $row['editor_uid']], TRUE)) {
      return FALSE;
    }
    $user = $this->entities->getStorage('user')->load($viewer->id());
    if (!$user || $user->isBlocked() || !$node->get('field_shooting_document')->access('view', $viewer) ||
      !$node->get('field_shooting_document')->access('edit', $viewer)) {
      return FALSE;
    }
    // Owners retain their effective OAuth scope; delegates receive only the selected draft grant.
    if ((string) $viewer->id() === (string) $node->getOwnerId()) {
      return $node->access('view', $viewer) && $node->access('update', $viewer);
    }
    return $this->asOwner($node, fn() => $node->access('update', $this->account) &&
      $node->get('field_shooting_document')->access('edit', $this->account));
  }

  private function row(string $uuid): array {
    return $this->database->select(self::TABLE, 's')->fields('s')->condition('plan_uuid', $uuid)
      ->execute()->fetchAssoc() ?: [];
  }

  private function asOwner(NodeInterface $node, callable $operation): mixed {
    $users = $this->entities->getStorage('user');
    $users->resetCache([$node->getOwnerId()]);
    $owner = $users->load($node->getOwnerId());
    if (!$owner || $owner->isBlocked()) {
      throw new NotFoundHttpException();
    }
    $this->switcher->switchTo($owner);
    try {
      return $operation();
    }
    finally {
      $this->switcher->switchBack();
    }
  }

}
