<?php

declare(strict_types=1);

namespace Drupal\xinshi_office;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;

/** Owner-bound snapshots with compare-and-swap revisions and durable idempotency receipts. */
final class OfficeDocuments {

  public function __construct(
    private readonly Connection $database,
    private readonly OfficeAccess $access,
    private readonly OfficeTypes $types,
    private readonly OfficeFiles $files,
    private readonly UuidInterface $uuid,
  ) {}

  /** Commits one complete working copy and its receipt in the same database transaction. */
  public function write(?string $id, \stdClass $input): array {
    $uid = $this->access->owner();
    if ($id !== NULL) OfficeInput::uuid($id);
    OfficeInput::object($input, ['operationId', 'name', 'content', 'assetIds', 'source',
      ...($id === NULL ? ['kind', 'chatId', 'originalFileId', 'importReport'] : ['expectedRevision'])]);
    OfficeInput::uuid($input->operationId);
    OfficeInput::text($input->name, 200, TRUE);
    if (!in_array($input->source, ['manual', 'import', 'ai', 'restore'], TRUE) ||
      ($id !== NULL && (!is_int($input->expectedRevision) || $input->expectedRevision < 1 ||
        $input->expectedRevision >= 2147483647 || $input->source === 'import'))) {
      throw new OfficeException('invalid_input');
    }
    $request_sha = hash('sha256', OfficeInput::encode((object) ['target' => $id, 'input' => $input]));
    $transaction = $this->database->startTransaction();
    try {
      $this->access->lock($uid);
      $previous = $this->database->select('xinshi_office_operation', 'o')->fields('o')
        ->condition('operation_id', $input->operationId)->execute()->fetchAssoc();
      if ($previous) {
        if ((int) $previous['uid'] !== $uid || !hash_equals($previous['input_sha256'], $request_sha)) {
          throw new OfficeException('operation_id_conflict', 409);
        }
        $result = $this->receipt($previous);
        unset($transaction);
        return $result;
      }
      $current = $id === NULL ? NULL : $this->owned($id);
      $kind = $current['kind'] ?? $input->kind;
      $this->access->write($kind);
      if ($current && (int) $current['current_revision'] !== $input->expectedRevision) {
        throw new OfficeException('revision_conflict', 409);
      }
      $expected_assets = $this->types->get($kind)->validate($input->content);
      $this->assets($kind, $input->assetIds, $expected_assets);
      if (!$current) {
        $this->access->conversation($input->chatId);
        $this->origin($kind, $input);
      }
      $payload = OfficeInput::encode((object) ['name' => $input->name,
        'content' => $input->content, 'assetIds' => $input->assetIds]);
      $this->access->quota($uid, strlen($payload), !$current);
      $document_id = $id ?? $this->uuid->generate();
      $revision = $current ? $input->expectedRevision + 1 : 1;
      $now = time();
      $sha = hash('sha256', $payload);
      if ($current) {
        $changed = $this->database->update('xinshi_office_document')->fields([
          'current_revision' => $revision, 'name' => $input->name, 'updated' => $now,
        ])->condition('document_id', $id)->condition('uid', $uid)
          ->condition('current_revision', $input->expectedRevision)->execute();
        if ($changed !== 1) {
          throw new OfficeException('revision_conflict', 409);
        }
      }
      else {
        $this->database->insert('xinshi_office_document')->fields([
          'document_id' => $document_id, 'uid' => $uid, 'kind' => $kind, 'name' => $input->name,
          'chat_id' => $input->chatId, 'original_file_id' => $input->originalFileId,
          'import_report' => $input->importReport === NULL ? NULL : OfficeInput::encode($input->importReport),
          'current_revision' => $revision, 'created' => $now, 'updated' => $now,
        ])->execute();
      }
      $this->database->insert('xinshi_office_revision')->fields([
        'document_id' => $document_id, 'revision' => $revision, 'uid' => $uid,
        'name' => $input->name, 'payload_json' => $payload, 'sha256' => $sha,
        'bytes' => strlen($payload), 'source' => $input->source, 'created' => $now,
      ])->execute();
      $operation = ['operation_id' => $input->operationId, 'uid' => $uid,
        'document_id' => $document_id, 'revision' => $revision, 'input_sha256' => $request_sha, 'created' => $now];
      $this->database->insert('xinshi_office_operation')->fields($operation)->execute();
      $result = $this->receipt($operation);
      unset($transaction);
      return $result;
    }
    catch (\Throwable $error) {
      if (isset($transaction)) {
        $transaction->rollBack();
        unset($transaction);
      }
      throw $error;
    }
  }

  /** Reads a current or immutable historical snapshot under the current owner permission. */
  public function read(string $id, ?int $revision = NULL): array {
    // Explicit historical reads remain available for submitted-write reconciliation.
    $document = $this->owned($id, $revision !== NULL);
    $revision ??= (int) $document['current_revision'];
    $row = $this->revision($id, $revision);
    $payload = json_decode($row['payload_json'], FALSE, 64, JSON_THROW_ON_ERROR);
    return ['version' => 1, 'documentId' => $id, 'kind' => $document['kind'], 'name' => $payload->name,
      'chatId' => $document['chat_id'], 'revision' => $revision,
      'currentRevision' => (int) $document['current_revision'], 'sha256' => $row['sha256'],
      'content' => $payload->content, 'assets' => array_map($this->files->metadata(...), $payload->assetIds),
      'original' => $document['original_file_id'] ? $this->files->metadata($document['original_file_id']) : NULL,
      'importReport' => $document['import_report'] ? json_decode($document['import_report'], FALSE, 16, JSON_THROW_ON_ERROR) : NULL,
      'source' => $row['source'], 'created' => (int) $document['created'], 'updated' => (int) $row['created']];
  }

  /** Lists a bounded owner page without loading snapshot bodies or private bytes. */
  public function listing(?string $after = NULL, ?string $chat = NULL): array {
    $uid = $this->access->owner();
    $query = $this->database->select('xinshi_office_document', 'd')
      ->fields('d', ['document_id', 'name', 'kind', 'chat_id', 'current_revision', 'created', 'updated'])
      ->condition('uid', $uid)->condition('deleted', 0)->orderBy('document_id')->range(0, 26);
    if ($after !== NULL) { OfficeInput::uuid($after); $query->condition('document_id', $after, '>'); }
    if ($chat !== NULL) { OfficeInput::uuid($chat); $query->condition('chat_id', $chat); }
    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $more = count($rows) > 25;
    $rows = array_slice($rows, 0, 25);
    return ['items' => array_map(static fn(array $row) => [
      'documentId' => $row['document_id'], 'name' => $row['name'], 'kind' => $row['kind'],
      'chatId' => $row['chat_id'], 'revision' => (int) $row['current_revision'],
      'created' => (int) $row['created'], 'updated' => (int) $row['updated'],
    ], $rows), 'nextAfter' => $more ? end($rows)['document_id'] : NULL];
  }

  /** Lists version metadata without returning all historical document bodies. */
  public function revisions(string $id, ?int $before = NULL): array {
    $this->owned($id);
    $query = $this->database->select('xinshi_office_revision', 'r')
      ->fields('r', ['revision', 'name', 'sha256', 'source', 'created'])
      ->condition('document_id', $id)->orderBy('revision', 'DESC')->range(0, 26);
    if ($before !== NULL) $query->condition('revision', $before, '<');
    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $more = count($rows) > 25;
    $rows = array_slice($rows, 0, 25);
    return ['items' => array_map(static fn(array $row) => [
      'revision' => (int) $row['revision'], 'name' => $row['name'], 'sha256' => $row['sha256'],
      'source' => $row['source'], 'created' => (int) $row['created'],
    ], $rows), 'nextBefore' => $more ? (int) end($rows)['revision'] : NULL];
  }

  /** Returns a submitted write receipt without re-executing any operation. */
  public function find(string $operation_id): ?array {
    OfficeInput::uuid($operation_id);
    $uid = $this->access->owner();
    $row = $this->database->select('xinshi_office_operation', 'o')->fields('o')
      ->condition('operation_id', $operation_id)->condition('uid', $uid)->execute()->fetchAssoc();
    return $row ? $this->receipt($row) : NULL;
  }

  /** Requires both owner access and membership in the requested document revision. */
  public function file(string $id, string $file_id, ?int $revision = NULL): array {
    OfficeInput::uuid($file_id);
    $document = $this->owned($id, $revision !== NULL);
    $row = $this->revision($id, $revision ?? (int) $document['current_revision']);
    $payload = json_decode($row['payload_json'], FALSE, 64, JSON_THROW_ON_ERROR);
    if ($document['original_file_id'] !== $file_id && !in_array($file_id, $payload->assetIds, TRUE)) {
      throw new OfficeException('file_unavailable', 404);
    }
    return $this->files->download($file_id);
  }

  private function owned(string $id, bool $historical = FALSE): array {
    OfficeInput::uuid($id);
    $uid = $this->access->owner();
    $row = $this->database->select('xinshi_office_document', 'd')->fields('d')
      ->condition('document_id', $id)->condition('uid', $uid)->execute()->fetchAssoc();
    if (!$row || (!$historical && (int) $row['deleted'] !== 0)) {
      throw new OfficeException('document_unavailable', 404);
    }
    return $row;
  }

  private function revision(string $id, int $revision): array {
    $row = $this->database->select('xinshi_office_revision', 'r')->fields('r')
      ->condition('document_id', $id)->condition('revision', $revision)->execute()->fetchAssoc();
    if (!$row) throw new OfficeException('revision_unavailable', 404);
    return $row;
  }

  private function receipt(array $operation): array {
    $this->owned($operation['document_id'], TRUE);
    $sha = $this->database->select('xinshi_office_revision', 'r')->fields('r', ['sha256'])
      ->condition('document_id', $operation['document_id'])->condition('revision', $operation['revision'])
      ->execute()->fetchField();
    if (!$sha) throw new OfficeException('operation_unavailable', 503);
    return ['operationId' => $operation['operation_id'], 'documentId' => $operation['document_id'],
      'revision' => (int) $operation['revision'], 'sha256' => $sha, 'inputSha256' => $operation['input_sha256']];
  }

  private function assets(string $kind, mixed $ids, array $expected): void {
    if (!is_array($ids) || !array_is_list($ids) || count($ids) > 20) {
      throw new OfficeException('invalid_reference');
    }
    $seen = $digests = [];
    $pixels = 0;
    foreach ($ids as $id) {
      OfficeInput::uuid($id);
      if (isset($seen[$id])) throw new OfficeException('invalid_reference');
      $seen[$id] = TRUE;
      $file = $this->files->metadata($id);
      if ($file['kind'] !== $kind || $file['role'] !== 'image' || isset($digests[$file['sha256']])) {
        throw new OfficeException('invalid_reference');
      }
      $digests[$file['sha256']] = TRUE;
      $pixels += $file['width'] * $file['height'];
    }
    if ($pixels > 32000000 || array_diff($expected, array_keys($digests)) || array_diff(array_keys($digests), $expected)) {
      throw new OfficeException('invalid_reference');
    }
  }

  private function origin(string $kind, \stdClass $input): void {
    if ($input->originalFileId === NULL) {
      if ($input->importReport !== NULL || $input->source === 'import') throw new OfficeException('invalid_origin');
      return;
    }
    OfficeInput::uuid($input->originalFileId);
    $file = $this->files->metadata($input->originalFileId);
    $report = $input->importReport;
    OfficeInput::object($report, ['sourceSha256', 'importer', 'issues']);
    OfficeInput::text($report->importer, 128, TRUE);
    if ($file['kind'] !== $kind || $file['role'] !== 'original' || $report->sourceSha256 !== $file['sha256'] ||
      $input->source !== 'import' || !is_array($report->issues) || !array_is_list($report->issues) || count($report->issues) > 100) {
      throw new OfficeException('invalid_origin');
    }
    foreach ($report->issues as $issue) {
      OfficeInput::object($issue, ['code', 'part', 'severity']);
      OfficeInput::text($issue->code, 100, TRUE);
      OfficeInput::text($issue->part, 512, TRUE);
      if ($issue->severity !== 'simplify') throw new OfficeException('unsupported_import');
    }
  }

}
