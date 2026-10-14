<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_knowledge\Knowledge;

/** Metadata-only catalog, independent of extraction completion and search indexing. */
final class KnowledgeCatalog {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly AttachmentExtraction $extraction,
    private readonly ModuleHandlerInterface $modules,
    private readonly Connection $database,
  ) {}

  /** Scan a bounded page; inaccessible rows never prevent advancing the cursor. */
  public function listing(AccountInterface $account, int $before = 0): array {
    $storage = $this->entities->getStorage('node');
    $query = $storage->getQuery()->accessCheck(TRUE)->condition('type', Knowledge::NODE)
      ->sort('nid', 'DESC')->range(0, 21);
    if ($before > 0) {
      $query->condition('nid', $before, '<');
    }
    $ids = array_values($query->execute());
    $page = array_slice($ids, 0, 20);
    $items = [];
    foreach ($storage->loadMultiple($page) as $node) {
      if ($row = $this->describe($node, $account)) {
        $items[] = $row;
      }
    }
    return ['items' => $items, 'next' => count($ids) > 20 ? (int) end($page) : NULL];
  }

  /** Resolve only default revisions; a UUID is never an access grant. */
  public function detail(string $uuid, AccountInterface $account): ?array {
    $nodes = $this->entities->getStorage('node')->loadByProperties([
      'type' => Knowledge::NODE, 'uuid' => $uuid,
    ]);
    $node = reset($nodes);
    return $node instanceof NodeInterface ? $this->describe($node, $account) : NULL;
  }

  /** Do not read extracted text, private paths or parser internals for a status response. */
  private function describe(NodeInterface $node, AccountInterface $account): ?array {
    if (!$account->isAuthenticated() || !$account->hasPermission('view xinshi knowledge') ||
        !$node->isDefaultRevision() || !$node->access('view', $account) ||
        !$node->get('title')->access('view', $account)) {
      return NULL;
    }
    $owner = NULL;
    if ($node->get('uid')->access('view', $account)) {
      $user = $node->getOwner();
      if ($user && $user->access('view', $account) && $user->get('name')->access('view', $account)) {
        $owner = $user->getAccountName();
      }
    }
    $attachments = [];
    $restricted = !$node->get(Knowledge::ATTACHMENTS)->access('view', $account);
    if (!$restricted) {
      foreach ($node->get(Knowledge::ATTACHMENTS) as $reference) {
        $media = $reference->entity;
        if (!$media instanceof MediaInterface || $media->bundle() !== Knowledge::MEDIA ||
            !$media->access('view', $account) || !$media->get('name')->access('view', $account) ||
            !$media->get(Knowledge::FILE)->access('view', $account)) {
          $restricted = TRUE;
          continue;
        }
        $file = $this->extraction->file($media);
        if ($file) {
          if (!$file->access('download', $account) || !$file->get('filename')->access('view', $account)) {
            $restricted = TRUE;
            continue;
          }
          $headers = $this->modules->invokeAll('file_download', [$file->getFileUri()]);
          if (!$headers || in_array(-1, $headers, TRUE)) {
            $restricted = TRUE;
            continue;
          }
        }
        $state = $this->database->select(Knowledge::TABLE, 'e')
          ->fields('e', ['source_key', 'status', 'error'])
          ->condition('mid', $media->id())->execute()->fetchAssoc();
        $current = $file && $state &&
          $state['source_key'] === $this->extraction->sourceKey($media, $file);
        $missing = !$file || !is_file($file->getFileUri());
        $attachments[] = [
          'id' => $media->uuid(), 'name' => $media->label(),
          'status' => $missing ? 'failed' : ($current ? $state['status'] : 'pending'),
          'error' => $missing ? 'file_missing' : ($current ? $state['error'] : ''),
        ];
      }
    }
    return [
      'id' => $node->uuid(), 'title' => $node->label(), 'owner' => $owner,
      'created' => $node->get('created')->access('view', $account)
        ? gmdate('c', (int) $node->getCreatedTime()) : NULL,
      'attachments' => $attachments, 'restricted' => $restricted,
    ];
  }

}
