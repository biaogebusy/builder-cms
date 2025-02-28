<?php

namespace Drupal\comment_delete;

/**
 * Provides comment delete constants.
 */
class CommentDeleteConstants {

  /**
   * Get available comment delete operations.
   *
   * @return array
   *   Returns an associative array of delete operations.
   */
  public static function getOperations(): array {
    return [
      'hard' => t('Delete comment and its replies (hard delete)'),
      'hard_partial' => t('Delete comment and move its replies up one thread level (partial hard delete)'),
      'soft' => t('Delete comment and keep its replies (soft delete)'),
    ];
  }

}
