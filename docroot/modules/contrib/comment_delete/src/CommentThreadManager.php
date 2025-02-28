<?php

namespace Drupal\comment_delete;

use Drupal\Component\Utility\Number;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides comment thread manager.
 */
class CommentThreadManager implements CommentThreadManagerInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * CommentThreadManager constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function calculate(EntityInterface $entity): void {
    $tags = [];

    // Get comments to recalculate threads. Save precious compute cycles using
    // direct comment data table query. Threads are updated without having
    // to save every comment entity. However, every comment cache tag is
    // invalidated because the thread changes render position.
    $query = $this->connection->select('comment_field_data')
      ->fields('comment_field_data', ['cid', 'pid'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->orderBy('created');

    if ($comments = $query->execute()->fetchAllAssoc('cid')) {
      foreach ($this->getThreads($this->getTree($comments)) as $cid => $thread) {
        $tags["comment:$cid"] = "comment:$cid";
        $this->connection->update('comment_field_data')
          ->fields(['thread' => $thread])
          ->condition('cid', $cid)
          ->execute();
      }
      Cache::invalidateTags($tags);
    }
  }

  /**
   * Get threaded comments tree from flat array.
   *
   * @param array $comments
   *   A flat array of threaded comments.
   * @param int $pid
   *   The parent comment ID.
   *
   * @return array
   *   Returns an array of threaded comments.
   */
  protected function getTree(array $comments, int $pid = 0): array {
    $branch = [];
    foreach ($comments as $comment) {
      if ((int) $comment->pid === $pid) {
        if ($children = $this->getTree($comments, (int) $comment->cid)) {
          $comment->children = $children;
        }
        $branch[] = $comment;
      }
    }
    return $branch;
  }

  /**
   * Get threads from comments tree.
   *
   * @param array $tree
   *   An array of nested comments.
   * @param string|null $thread
   *   The parent threads.
   * @param bool $top
   *   Boolean indicating the topmost thread.
   *
   * @return array
   *   Returns an associative array of comment threads.
   */
  protected function getThreads(array $tree, string $thread = NULL, bool $top = TRUE): array {
    $threads = [];
    $level = $top ? 1 : 0;
    foreach ($tree as $comment) {
      $threadLevel = ltrim("$thread." . Number::intToAlphadecimal(sprintf('%02d', str_pad($level, 1, '0', STR_PAD_LEFT))), '.');
      $threads[$comment->cid] = "$threadLevel/";
      if (isset($comment->children)) {
        $threads += $this->getThreads($comment->children, $threadLevel, FALSE);
      }
      $level++;
    }
    return $threads;
  }

}
