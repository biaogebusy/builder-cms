<?php

declare(strict_types=1);

namespace Drupal\xinshi_office;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/** Current effective-account authorization, type policy and serialized owner quotas. */
final class OfficeAccess {

  public function __construct(
    private readonly AccountInterface $account,
    private readonly EntityTypeManagerInterface $entities,
    private readonly ConfigFactoryInterface $config,
    private readonly Connection $database,
    private readonly OfficeTypes $types,
  ) {}

  /** Checks the live account while retaining OAuth scope in permission decisions. */
  public function owner(): int {
    if (!$this->account->isAuthenticated() || !$this->account->hasPermission('read own office documents')) {
      throw new OfficeException('permission_denied', 403);
    }
    $storage = $this->entities->getStorage('user');
    $storage->resetCache([$this->account->id()]);
    $user = $storage->load($this->account->id());
    if (!$user || $user->isBlocked()) {
      throw new OfficeException('permission_denied', 403);
    }
    return (int) $this->account->id();
  }

  /** Requires a write grant and an enabled, registered document kind. */
  public function write(mixed $kind): int {
    $uid = $this->owner();
    $this->types->get($kind);
    $enabled = $this->config->get('xinshi_office.settings')->get('write_types');
    if (!$this->account->hasPermission('edit own office documents') ||
      !is_array($enabled) || !in_array($kind, $enabled, TRUE)) {
      throw new OfficeException('write_disabled', 403);
    }
    return $uid;
  }

  /** Returns UI capabilities without granting authority to client-selected types. */
  public function capabilities(): array {
    $uid = $this->owner();
    $writable = [];
    foreach ($this->types->kinds() as $kind) {
      try {
        $this->write($kind);
        $writable[] = $kind;
      }
      catch (OfficeException) {}
    }
    return ['version' => 1, 'uid' => (string) $uid, 'writeTypes' => $writable,
      'maxStorageBytes' => $this->limit('max_storage_bytes'), 'maxDocuments' => $this->limit('max_documents')];
  }

  /** Locks one existing user row inside the caller's transaction to serialize quota and CAS writes. */
  public function lock(int $uid): void {
    $found = $this->database->select('users', 'u')->fields('u', ['uid'])
      ->condition('uid', $uid)->forUpdate()->execute()->fetchField();
    if (!$found || $this->owner() !== $uid) {
      throw new OfficeException('permission_denied', 403);
    }
  }

  /** Checks retained bytes before a new file or revision; never deletes earlier content. */
  public function quota(int $uid, int $bytes, bool $new_document = FALSE): void {
    $used = 0;
    foreach (['xinshi_office_file', 'xinshi_office_revision'] as $table) {
      $query = $this->database->select($table, 's')->condition('uid', $uid);
      $query->addExpression('SUM(bytes)', 'used');
      $used += (int) $query->execute()->fetchField();
    }
    if ($used + $bytes > $this->limit('max_storage_bytes') || ($new_document &&
      (int) $this->database->select('xinshi_office_document', 'd')->condition('uid', $uid)
        ->countQuery()->execute()->fetchField() >= $this->limit('max_documents'))) {
      throw new OfficeException('storage_limit', 409);
    }
  }

  /** Validates optional chat binding at creation; the binding never grants document access. */
  public function conversation(mixed $id): void {
    if ($id === NULL) {
      return;
    }
    OfficeInput::uuid($id);
    $nodes = $this->entities->getStorage('node')->loadByProperties(['type' => 'conversation', 'uuid' => $id]);
    $node = $nodes ? reset($nodes) : NULL;
    if (!$node || (int) $node->getOwnerId() !== $this->owner() || !$node->access('view', $this->account) ||
      !$node->get('uuid')->access('view', $this->account)) {
      throw new OfficeException('conversation_unavailable', 403);
    }
  }

  private function limit(string $key): int {
    $value = $this->config->get('xinshi_office.settings')->get($key);
    if (!is_int($value) || $value < 1 || $value > 2147483647) {
      throw new OfficeException('configuration_unavailable', 503);
    }
    return $value;
  }

}

