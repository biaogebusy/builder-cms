<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\Service;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/** Keeps unpublished and published chat storage private, including older revisions. */
final class ChatReadAccess {

  private const BUNDLES = ['conversation', 'ai_session'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly Connection $database,
  ) {}

  /** Add a restriction without granting any of Drupal's entity or field permissions. */
  public function view(NodeInterface $node, AccountInterface $account): AccessResultInterface {
    if (!in_array($node->bundle(), self::BUNDLES, TRUE)) {
      return AccessResult::neutral();
    }
    $result = AccessResult::neutral()->cachePerUser()->cachePerPermissions()
      ->setCacheMaxAge(0)->addCacheableDependency($node);
    if ($account->isAuthenticated() && $account->hasPermission('bypass node access')) {
      return $result;
    }
    $denied = $account->isAnonymous() || (string) $account->id() !== (string) $node->getOwnerId();
    if (!$node->isNew() && !$node->isDefaultRevision()) {
      $current = $this->entities->getStorage('node')->loadUnchanged($node->id());
      $result->addCacheableDependency($current);
      $denied = $denied || !$current || (string) $current->getOwnerId() !== (string) $account->id();
    }
    return $denied ? AccessResult::forbidden()->addCacheableDependency($result) : $result;
  }

  /** Filter before pagination and field-based Views, using the effective OAuth principal. */
  public function query(AlterableInterface $query, AccountInterface $account): void {
    if (($query->getMetaData('op') ?? 'view') !== 'view' ||
      ($account->isAuthenticated() && $account->hasPermission('bypass node access'))) {
      return;
    }
    foreach ($query->getTables() as $alias => $table) {
      if (!in_array($table['table'], ['node', 'node_field_data', 'node_revision', 'node_field_revision'], TRUE)) {
        continue;
      }
      $private = $this->database->select('node_field_data', 'chat_private')
        ->fields('chat_private', ['nid'])->condition('type', self::BUNDLES, 'IN');
      if ($account->isAuthenticated()) {
        $private->condition('uid', $account->id(), '<>');
      }
      $query->condition($alias . '.nid', $private, 'NOT IN');
      if (in_array($table['table'], ['node_revision', 'node_field_revision'], TRUE)) {
        $revisions = $this->database->select('node_field_revision', 'chat_private_revision')
          ->fields('chat_private_revision', ['vid'])->condition('type', self::BUNDLES, 'IN');
        if ($account->isAuthenticated()) {
          $revisions->condition('uid', $account->id(), '<>');
        }
        $query->condition($alias . '.vid', $revisions, 'NOT IN');
      }
    }
  }

}
