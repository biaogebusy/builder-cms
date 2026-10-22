<?php

declare(strict_types=1);

namespace Drupal\xinshi_office;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Explicit administrative access; owner APIs never borrow these permissions. */
final class OfficeAdministration {

  public function __construct(
    private readonly Connection $database,
    private readonly AccountInterface $account,
    private readonly EntityTypeManagerInterface $entities,
    private readonly FileSystemInterface $filesystem,
    private readonly LoggerInterface $logger,
  ) {}

  /** Recheck the live account and effective permissions at every service entry. */
  public function authorize(bool $delete = FALSE): void {
    if (!$this->account->isAuthenticated() || !$this->account->hasPermission('view all office documents') ||
      ($delete && !$this->account->hasPermission('delete office documents'))) {
      throw new AccessDeniedHttpException();
    }
    $storage = $this->entities->getStorage('user');
    $storage->resetCache([$this->account->id()]);
    $user = $storage->load($this->account->id());
    if (!$user || $user->isBlocked()) throw new AccessDeniedHttpException();
  }

  /** Page through metadata without loading document bodies or private file bytes. */
  public function listing(string $name, string $kind, int $uid, bool $deleted): array {
    $this->authorize();
    $query = $this->database->select('xinshi_office_document', 'd')
      ->fields('d', ['document_id', 'uid', 'kind', 'name', 'current_revision', 'created', 'updated', 'deleted'])
      ->condition('deleted', 0, $deleted ? '>' : '=');
    if ($name !== '') $query->condition('name', '%' . $this->database->escapeLike($name) . '%', 'LIKE');
    if ($kind !== '') $query->condition('kind', $kind);
    if ($uid > 0) $query->condition('uid', $uid);
    return $query->orderBy('updated', 'DESC')->orderBy('document_id')
      ->extend(PagerSelectExtender::class)->limit(25)->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** Load one current or historical snapshot, including documents in the trash. */
  public function document(string $id, ?int $revision = NULL): array {
    $this->authorize();
    $document = $this->row($id);
    $query = $this->database->select('xinshi_office_revision', 'r')->fields('r')
      ->condition('document_id', $id)->condition('uid', $document['uid'])
      ->condition('revision', $revision ?? (int) $document['current_revision']);
    $row = $query->execute()->fetchAssoc();
    if (!$row) throw new NotFoundHttpException();
    return ['document' => $document, 'revision' => $row,
      'payload' => json_decode($row['payload_json'], TRUE, 64, JSON_THROW_ON_ERROR)];
  }

  /** Page through immutable version metadata. */
  public function revisions(string $id): array {
    $this->authorize();
    $this->row($id);
    return $this->database->select('xinshi_office_revision', 'r')
      ->fields('r', ['revision', 'name', 'source', 'bytes', 'created'])
      ->condition('document_id', $id)->orderBy('revision', 'DESC')
      ->extend(PagerSelectExtender::class)->limit(25)->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** Return only files belonging to this document and the requested revision. */
  public function files(string $id, int $revision): array {
    $snapshot = $this->document($id, $revision);
    $ids = $snapshot['payload']['assetIds'];
    if ($snapshot['document']['original_file_id']) $ids[] = $snapshot['document']['original_file_id'];
    if (!$ids) return [];
    return $this->database->select('xinshi_office_file', 'f')
      ->fields('f', ['file_id', 'name', 'role', 'mime', 'bytes', 'sha256'])
      ->condition('uid', $snapshot['document']['uid'])->condition('file_id', $ids, 'IN')
      ->execute()->fetchAllAssoc('file_id', \PDO::FETCH_ASSOC);
  }

  /** Membership and byte integrity remain mandatory for administrative downloads. */
  public function download(string $id, int $revision, string $file_id): array {
    $files = $this->files($id, $revision);
    if (!isset($files[$file_id])) throw new NotFoundHttpException();
    $uri = $this->database->select('xinshi_office_file', 'f')->fields('f', ['uri'])
      ->condition('file_id', $file_id)->execute()->fetchField();
    $path = $this->filesystem->realpath($uri);
    $file = $files[$file_id];
    if (!$path || !is_file($path) || filesize($path) !== (int) $file['bytes'] ||
      !hash_equals($file['sha256'], hash_file('sha256', $path))) throw new NotFoundHttpException();
    return $file + ['path' => $path];
  }

  /** Serialize deletion with owner writes and reject a stale confirmation. */
  public function changeStatus(string $id, bool $delete, int $revision, int $deleted): void {
    $this->authorize(TRUE);
    $document = $this->row($id);
    $transaction = $this->database->startTransaction();
    try {
      // Use the same owner row lock as uploads, document writes and quota checks.
      $this->database->select('users', 'u')->fields('u', ['uid'])->condition('uid', $document['uid'])
        ->forUpdate()->execute()->fetchField();
      $this->authorize(TRUE);
      $current = $this->row($id);
      if ((int) $current['current_revision'] !== $revision || (int) $current['deleted'] !== $deleted ||
        $delete === ((int) $current['deleted'] > 0)) throw new ConflictHttpException();
      $this->database->update('xinshi_office_document')->fields(['deleted' => $delete ? time() : 0])
        ->condition('document_id', $id)->execute();
      unset($transaction);
    }
    catch (\Throwable $error) {
      if (isset($transaction)) { $transaction->rollBack(); unset($transaction); }
      throw $error;
    }
    $this->logger->notice('Office document @document for account @owner was @action by account @actor.',
      ['@document' => $id, '@owner' => $document['uid'], '@action' => $delete ? 'trashed' : 'restored',
        '@actor' => $this->account->id()]);
  }

  private function row(string $id): array {
    $row = $this->database->select('xinshi_office_document', 'd')->fields('d')
      ->condition('document_id', $id)->execute()->fetchAssoc();
    if (!$row) throw new NotFoundHttpException();
    return $row;
  }

}
