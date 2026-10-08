<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge_sync\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\node\NodeInterface;

/** Applies complete source snapshots atomically and keeps a revision ownership ledger. */
final class SnapshotImporter {

  private bool $importing = FALSE;

  /** Allows managed-node writes only inside the authenticated import boundary. */
  public function isImporting(): bool {
    return $this->importing;
  }

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly Connection $database,
    private readonly LockBackendInterface $lock,
    private readonly SourceAccess $access,
    private readonly AccountSwitcherInterface $accounts,
    private readonly LanguageManagerInterface $languages,
  ) {}

  /** Preview by default; applying requires an active administrative account. */
  public function import(array $manifest, string $source, AccountInterface $account, bool $apply = FALSE, bool $allowEmpty = FALSE): array {
    $user = $this->entities->getStorage('user')->load($account->id());
    if (!$user || !$user->isActive() || !$account->hasPermission('administer nodes') ||
        !$account->hasPermission('administer xinshi knowledge')) {
      throw new \DomainException('import_forbidden');
    }
    $this->validate($manifest, $source, $allowEmpty);
    $lockName = 'xinshi_knowledge_sync.import';
    if (!$this->lock->acquire($lockName, 300)) {
      throw new \DomainException('sync_locked');
    }
    $started = microtime(TRUE);
    $transaction = NULL;
    $this->accounts->switchTo($account);
    $this->importing = TRUE;
    $this->entities->getAccessControlHandler('node')->resetCache();
    try {
      $storage = $this->entities->getStorage('node');
      $owned = $this->database->select(SourceAccess::TABLE, 'd')->fields('d')
        ->condition('source', $source)->condition('language', $manifest['language'])->range(0, 10001)
        ->execute()->fetchAllAssoc('document_key', \PDO::FETCH_ASSOC);
      if (count($owned) > 10000) {
        throw new \DomainException('source_ledger_limit');
      }
      $nodes = $storage->loadMultiple(array_column($owned, 'nid'));
      $plan = [];
      $stats = ['created' => 0, 'updated' => 0, 'unpublished' => 0, 'unchanged' => 0, 'applied' => $apply];
      foreach ($manifest['documents'] as $document) {
        $key = hash('sha256', $source . "\0" . $manifest['language'] . "\0" . $document['path']);
        $row = $owned[$key] ?? NULL;
        unset($owned[$key]);
        $node = $row ? ($nodes[$row['nid']] ?? NULL) : $storage->create([
          'type' => 'xinshi_knowledge', 'uid' => $account->id(), 'langcode' => $manifest['language'],
        ]);
        $this->checkNode($node, $row, $account);
        $policy = $this->access->policy($source, $document['path']);
        $action = !$row ? 'created' : ($row['content_hash'] === $document['hash'] &&
          $row['policy'] === $policy && $node->isPublished() ? 'unchanged' : 'updated');
        $stats[$action]++;
        if ($action !== 'unchanged') {
          $this->prepare($node, $document, $source, $manifest['revision'], $account);
        }
        $plan[] = compact('key', 'row', 'node', 'document', 'policy', 'action');
      }
      foreach ($owned as $key => $row) {
        $node = $nodes[$row['nid']] ?? NULL;
        $this->checkNode($node, $row, $account);
        if ($node->isPublished()) {
          $stats['unpublished']++;
          $this->prepare($node, NULL, $source, $manifest['revision'], $account);
          $plan[] = ['key' => $key, 'row' => $row, 'node' => $node, 'action' => 'unpublished'];
        }
      }
      if (!$apply) {
        return $stats;
      }
      $transaction = $this->database->startTransaction();
      foreach ($plan as $item) {
        if (microtime(TRUE) - $started > 180) {
          throw new \RuntimeException('sync_time_limit');
        }
        ['key' => $key, 'row' => $row, 'node' => $node, 'action' => $action] = $item;
        if ($action === 'unchanged') {
          continue;
        }
        $node->save();
        $values = [
          'source' => $source, 'language' => $manifest['language'],
          'path' => $item['document']['path'] ?? $row['path'],
          'policy' => $item['policy'] ?? $row['policy'], 'nid' => (int) $node->id(),
          'revision_id' => (int) $node->getRevisionId(),
          'content_hash' => $item['document']['hash'] ?? $row['content_hash'],
          'node_hash' => $this->nodeHash($node),
          'source_revision' => $manifest['revision'],
        ];
        $this->database->merge(SourceAccess::TABLE)->key('document_key', $key)->fields($values)->execute();
      }
      unset($transaction);
      return $stats;
    }
    catch (\Throwable $error) {
      if ($transaction) {
        $transaction->rollBack();
      }
      $this->entities->getStorage('node')->resetCache();
      throw $error;
    }
    finally {
      $this->importing = FALSE;
      $this->entities->getAccessControlHandler('node')->resetCache();
      $this->entities->getStorage('node')->resetCache();
      $this->accounts->switchBack();
      $this->lock->release($lockName);
    }
  }

  private function prepare(NodeInterface $node, ?array $document, string $source, string $revision, AccountInterface $account): void {
    $node->setNewRevision(TRUE);
    $node->setRevisionUserId($account->id());
    $node->setRevisionLogMessage('Knowledge sync: ' . $source . ' @ ' . $revision);
    if ($document === NULL) {
      $node->setUnpublished();
    }
    else {
      $text = $document['text'] . "\n\nSource: " . $document['url'];
      // Escaping preserves code-like text for rendering and the existing body extractor.
      $node->setTitle($document['title']);
      $node->set('body', ['value' => nl2br(Html::escape($text), FALSE), 'format' => 'basic_html']);
      $node->setPublished();
    }
    if (count($node->validate())) {
      throw new \DomainException('document_validation_failed');
    }
  }

  private function nodeHash(NodeInterface $node): string {
    $values = [];
    foreach (['title', 'body', 'langcode', 'uid', 'status', 'field_knowledge_documents', 'field_knowledge_category'] as $field) {
      $values[$field] = $node->get($field)->getValue();
    }
    $values['body'] = [];
    foreach ($node->get('body') as $item) {
      $values['body'][] = [
        'value' => (string) $item->value, 'summary' => (string) $item->summary,
        'format' => (string) $item->format,
      ];
    }
    array_walk_recursive($values, static function (&$value): void {
      if ($value !== NULL) {
        $value = is_bool($value) ? (string) (int) $value : (string) $value;
      }
    });
    return hash('sha256', serialize($values));
  }

  private function checkNode(?NodeInterface $node, ?array $row, AccountInterface $account): void {
    if (!$node || $node->bundle() !== 'xinshi_knowledge' ||
        ($row && ((string) $node->getRevisionId() !== (string) $row['revision_id'] ||
          (string) $this->entities->getStorage('node')->getLatestRevisionId($node->id()) !== (string) $row['revision_id'] ||
          !hash_equals($row['node_hash'], $this->nodeHash($node)) ||
          !$node->isDefaultRevision() || count($node->getTranslationLanguages()) !== 1 ||
          !$node->get('field_knowledge_documents')->isEmpty()))) {
      throw new \DomainException('source_revision_conflict');
    }
    if ($node->hasField('moderation_state')) {
      throw new \DomainException('moderated_source_not_supported');
    }
    $allowed = $row ? $node->access('update', $account) :
      $this->entities->getAccessControlHandler('node')->createAccess('xinshi_knowledge', $account);
    if (!$allowed || !$node->get('body')->access('edit', $account) || !$node->get('title')->access('edit', $account)) {
      throw new \DomainException('document_write_forbidden');
    }
    $format = $this->entities->getStorage('filter_format')->load('basic_html');
    if (!$format || !$format->status() || !$format->access('use', $account)) {
      throw new \DomainException('basic_html_format_required');
    }
  }

  private function validate(array $manifest, string $source, bool $allowEmpty): void {
    if (array_diff(array_keys($manifest), ['version', 'source', 'language', 'revision', 'documents']) ||
        ($manifest['version'] ?? NULL) !== 1 || ($manifest['source'] ?? NULL) !== $source ||
        !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $source) ||
        !is_string($manifest['language'] ?? NULL) || !$this->languages->getLanguage($manifest['language']) ||
        !is_string($manifest['revision'] ?? NULL) || $manifest['revision'] === '' || strlen($manifest['revision']) > 200 ||
        !is_array($manifest['documents'] ?? NULL) || !array_is_list($manifest['documents']) ||
        (!$allowEmpty && !count($manifest['documents'])) || count($manifest['documents']) > 5000 ||
        strlen(json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > 20 * 1024 * 1024) {
      throw new \InvalidArgumentException('invalid_snapshot');
    }
    $this->access->source($source);
    $paths = [];
    foreach ($manifest['documents'] as $document) {
      if (!is_array($document) || count($document) !== 5 || array_diff(array_keys($document), ['path', 'title', 'text', 'url', 'hash'])) {
        throw new \InvalidArgumentException('invalid_document');
      }
      foreach ($document as $value) {
        if (!is_string($value) || $value === '' || str_contains($value, "\0") || !mb_check_encoding($value, 'UTF-8')) {
          throw new \InvalidArgumentException('invalid_document_text');
        }
      }
      $path = $document['path'];
      $url = parse_url($document['url']);
      if (strlen($path) > 512 || str_starts_with($path, '/') || str_contains($path, '\\') ||
          array_intersect(explode('/', $path), ['', '.', '..']) || !preg_match('/\.mdx?$/D', $path) ||
          isset($paths[$path]) || mb_strlen($document['title']) > 255 || strlen($document['text']) > 1024 * 1024 ||
          !$url || !in_array($url['scheme'] ?? '', ['http', 'https'], TRUE) || empty($url['host']) ||
          isset($url['user']) || isset($url['pass']) || strlen($document['url']) > 4096 ||
          !hash_equals(hash('sha256', implode("\0", [$document['title'], $document['text'], $document['url']])), $document['hash'])) {
        throw new \InvalidArgumentException('invalid_document');
      }
      $paths[$path] = TRUE;
      $this->access->policy($source, $path);
    }
  }

}
