<?php

namespace Drupal\content_lock\Plugin\views\sort;

use Drupal\views\Attribute\ViewsSort;
use Drupal\views\Plugin\views\sort\Standard;

/**
 * Content lock sort.
 *
 * @ViewsSort("content_lock_sort")
 */
#[ViewsSort('content_lock_sort')]
class ContentLockSort extends Standard {

  /**
   * Query.
   */
  public function query(): void {
    $this->ensureMyTable();
    $this->query->addOrderBy($this->tableAlias, 'timestamp', $this->options['order']);
  }

}
