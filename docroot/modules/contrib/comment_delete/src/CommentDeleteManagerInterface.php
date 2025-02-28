<?php

namespace Drupal\comment_delete;

use Drupal\comment\CommentInterface;

/**
 * Provides interface for comment delete manager.
 */
interface CommentDeleteManagerInterface {

  /**
   * Get comment delete configuration.
   *
   * @param \Drupal\comment\CommentInterface|null $comment
   *   The comment entity. Defaults to current comment context.
   *
   * @return array
   *   Returns an associative array of delete configurations.
   */
  public function getConfig(CommentInterface $comment = NULL): array;

  /**
   * Delete comment and do something with its replies.
   *
   * @param \Drupal\comment\CommentInterface $comment
   *   The comment entity.
   * @param string $op
   *   The delete operation.
   */
  public function delete(CommentInterface $comment, string $op): void;

  /**
   * Delete comment and move its replies up one thread level.
   */
  public function moveReplies(): CommentDeleteManagerInterface;

  /**
   * Hard (permanently) delete comment.
   *
   * @param bool $thread
   *   Boolean indicating to recalculate comment threads.
   */
  public function hardDelete(bool $thread = FALSE): CommentDeleteManagerInterface;

  /**
   * Soft delete comment.
   */
  public function softDelete(): CommentDeleteManagerInterface;

}
