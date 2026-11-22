<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge_sync\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSwitcherInterface;

/** Binds immutable requests to atomic imports and independently recoverable indexing. */
final class DeliveryExecution {

  public const TABLE = 'xinshi_knowledge_sync_delivery';

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly LockBackendInterface $lock,
    private readonly SnapshotImporter $importer,
    private readonly AccountSwitcherInterface $accounts,
  ) {}

  /** Revalidates the account before exposing provenance, including failed receipts. */
  public function receipt(string $id, AccountInterface $account): ?array {
    $this->authorize($account);
    $this->id($id);
    $row = $this->database->select(self::TABLE, 'r')->fields('r')->condition('task_id', $id)
      ->execute()->fetchAssoc();
    if (!$row) {
      return NULL;
    }
    if ((int) $row['account_uid'] !== (int) $account->id()) {
      throw new \DomainException('delivery_account_mismatch');
    }
    return ['version' => 1, 'id' => $row['task_id'], 'requestSha256' => $row['request_hash'],
      'request' => json_decode($row['request'], TRUE, 16, JSON_THROW_ON_ERROR),
      'status' => $row['status'], 'result' => json_decode($row['result'], TRUE, 16, JSON_THROW_ON_ERROR),
      'error' => $row['error'], 'created' => (int) $row['created'],
      'finished' => $row['finished'] === NULL ? NULL : (int) $row['finished']];
  }

  /** Repeated task IDs only read receipts; they never repeat an execution. */
  public function execute(array $request, string $bytes, AccountInterface $account): array {
    $this->authorize($account);
    $request = $this->request($request);
    if (strlen($bytes) > 20 * 1024 * 1024 || !hash_equals($request['snapshotSha256'], hash('sha256', $bytes))) {
      throw new \InvalidArgumentException('delivery_snapshot_mismatch');
    }
    $manifest = json_decode($bytes, TRUE, 32, JSON_THROW_ON_ERROR);
    if (!is_array($manifest) || ($manifest['source'] ?? NULL) !== $request['source'] ||
        ($manifest['language'] ?? NULL) !== $request['language'] || ($manifest['revision'] ?? NULL) !== $request['revision']) {
      throw new \InvalidArgumentException('delivery_snapshot_mismatch');
    }
    $requestHash = hash('sha256', json_encode([$request, (int) $account->id()], JSON_THROW_ON_ERROR));
    // Admission shares the import lock, then persists a non-expiring exclusion row.
    $lockName = 'xinshi_knowledge_sync.import';
    if (!$this->lock->acquire($lockName, 300)) {
      throw new \DomainException('sync_locked');
    }
    try {
      $previous = $this->receipt($request['id'], $account);
      if ($previous) {
        if (!hash_equals($previous['requestSha256'], $requestHash)) {
          throw new \DomainException('delivery_request_conflict');
        }
        return $previous;
      }
      if ($this->database->select(self::TABLE, 'r')->condition('status', 'running')
          ->range(0, 1)->countQuery()->execute()->fetchField()) {
        throw new \DomainException('delivery_unresolved');
      }
      $parent = NULL;
      if ($request['action'] !== 'preview') {
        $parent = $this->receipt($request['parentId'], $account);
        $expected = $request['action'] === 'import' ? 'preview' : 'import';
        if (!$parent || $parent['status'] !== 'succeeded' || $parent['request']['action'] !== $expected ||
            array_diff_assoc($this->binding($request), $this->binding($parent['request']))) {
          throw new \DomainException('delivery_parent_mismatch');
        }
      }
      $this->database->insert(self::TABLE)->fields([
        'task_id' => $request['id'], 'request_hash' => $requestHash,
        'request' => json_encode($request, JSON_THROW_ON_ERROR), 'account_uid' => (int) $account->id(),
        'status' => 'running', 'result' => '{}', 'error' => '', 'created' => time(),
      ])->execute();
    }
    finally {
      $this->lock->release($lockName);
    }
    $transaction = NULL;
    try {
      // The success receipt and Drupal content commit in the same database transaction.
      $transaction = $this->database->startTransaction();
      $planHash = '';
      $stats = $this->importer->import($manifest, $request['source'], $account, $request['action'] === 'import', FALSE,
        static function (array $stats, string $hash) use ($request, $parent, &$planHash): void {
          $planHash = $hash;
          if ($stats['unpublished'] > $request['maxUnpublished']) {
            throw new \DomainException('delivery_unpublish_limit');
          }
          if ($parent && !hash_equals($parent['result']['planSha256'], $hash)) {
            throw new \DomainException('delivery_plan_changed');
          }
        }, $request['id']);
      $result = ['stats' => $stats, 'planSha256' => $planHash];
      if ($request['action'] === 'import') {
        // Index recovery binds the committed node/ledger state, including unchanged documents.
        $this->importer->import($manifest, $request['source'], $account, FALSE, FALSE,
          static function (array $stats, string $hash) use (&$result): void {
            $result['planSha256'] = $hash;
          }, $request['id']);
      }
      if ($request['action'] === 'index') {
        // An import changes ledger revisions, so indexing validates the resulting content below.
        $result['index'] = $this->index($account);
      }
      $this->finish($request['id'], 'succeeded', $result, '');
      unset($transaction);
    }
    catch (\Throwable $error) {
      if ($transaction) {
        $transaction->rollBack();
        unset($transaction);
      }
      $code = $error instanceof \DomainException ? $error->getMessage() : 'delivery_execution_failed';
      $this->finish($request['id'], 'failed', [], preg_match('/^[a-z_]{1,64}$/D', $code) ? $code : 'delivery_execution_failed');
    }
    return $this->receipt($request['id'], $account);
  }

  private function index(AccountInterface $account): array {
    $index = $this->entities->getStorage('search_api_index')->load('xinshi_knowledge');
    if (!$index || !$index->status() || !$index->hasValidTracker() || $index->isReadOnly() ||
        !$index->getServerInstance()?->isAvailable()) {
      throw new \DomainException('delivery_index_unavailable');
    }
    $lockName = 'xinshi_knowledge_sync.import';
    if (!$this->lock->acquire($lockName, 300)) {
      throw new \DomainException('sync_locked');
    }
    $started = microtime(TRUE);
    $this->accounts->switchTo($account);
    try {
      $processed = 0;
      while (($remaining = (int) $index->getTrackerInstance()->getRemainingItemsCount()) > 0) {
        if (microtime(TRUE) - $started > 180 || !($batch = $index->indexItems(100))) {
          throw new \DomainException('delivery_index_incomplete');
        }
        $processed += $batch;
      }
      return ['id' => 'xinshi_knowledge', 'processed' => $processed, 'remaining' => $remaining];
    }
    finally {
      $this->accounts->switchBack();
      $this->lock->release($lockName);
    }
  }

  private function finish(string $id, string $status, array $result, string $error): void {
    $updated = $this->database->update(self::TABLE)->fields(['status' => $status,
      'result' => json_encode($result, JSON_THROW_ON_ERROR), 'error' => $error, 'finished' => time()])
      ->condition('task_id', $id)->condition('status', 'running')->execute();
    if ($updated !== 1) {
      throw new \DomainException('delivery_receipt_conflict');
    }
  }

  private function authorize(AccountInterface $account): void {
    $this->entities->getStorage('user')->resetCache([$account->id()]);
    $user = $this->entities->getStorage('user')->load($account->id());
    if (!$user || !$user->isActive() || !$user->hasPermission('administer nodes') ||
        !$user->hasPermission('administer xinshi knowledge') || !$account->hasPermission('administer nodes') ||
        !$account->hasPermission('administer xinshi knowledge')) {
      throw new \DomainException('import_forbidden');
    }
  }

  private function id(string $id): void {
    if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $id)) {
      throw new \InvalidArgumentException('invalid_delivery_id');
    }
  }

  private function binding(array $request): array {
    return array_intersect_key($request, array_flip(['source', 'language', 'revision', 'snapshotSha256', 'sourceConfigSha256', 'maxUnpublished']));
  }

  private function request(array $request): array {
    $keys = ['version', 'id', 'action', 'source', 'language', 'revision', 'snapshotSha256', 'sourceConfigSha256', 'maxUnpublished', 'parentId'];
    if (array_diff(array_keys($request), $keys) || count(array_diff($keys, array_keys($request))) ||
        $request['version'] !== 1 || !is_string($request['id']) ||
        !in_array($request['action'], ['preview', 'import', 'index'], TRUE) ||
        !is_string($request['source']) || !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $request['source']) ||
        !is_string($request['language']) || !preg_match('/^[a-z][a-z0-9-]{0,31}$/D', $request['language']) ||
        !is_int($request['maxUnpublished']) || $request['maxUnpublished'] < 0 || $request['maxUnpublished'] > 5000 ||
        ($request['action'] === 'preview' ? $request['parentId'] !== NULL : !is_string($request['parentId']))) {
      throw new \InvalidArgumentException('invalid_delivery_request');
    }
    $this->id($request['id']);
    if ($request['parentId'] !== NULL) {
      $this->id($request['parentId']);
    }
    foreach (['revision' => 40, 'snapshotSha256' => 64, 'sourceConfigSha256' => 64] as $key => $length) {
      if (!is_string($request[$key]) || !preg_match('/^[a-f0-9]{' . $length . '}$/D', $request[$key])) {
        throw new \InvalidArgumentException('invalid_delivery_request');
      }
    }
    return array_replace(array_fill_keys($keys, NULL), $request);
  }

}
