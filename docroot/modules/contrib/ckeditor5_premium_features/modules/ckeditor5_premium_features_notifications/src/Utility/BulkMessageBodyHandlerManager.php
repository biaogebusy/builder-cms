<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Utility;

use Drupal\ckeditor5_premium_features\Utility\BulkMessageBodyHandlerInterface;

/**
 * The Bulk message body handler manager.
 */
class BulkMessageBodyHandlerManager {

  /**
   * Holds array of bulk message body handlers, keyed by priority.
   *
   * @var array
   */
  protected array $handlers;

  /**
   * Holds array of bulk message body handlers, sorted by priority.
   *
   * @var array|null
   */
  protected ?array $sortedHandlers;

  /**
   * Collecting all handlers.
   *
   * @param BulkMessageBodyHandlerInterface $bulkMessageBodyHandler
   *   Bulk message body handler.
   * @param int $priority
   *   The priority.
   *
   * @return BulkMessageBodyHandlerManager
   *   The bulk message body handler manager.
   */
  public function addHandler(BulkMessageBodyHandlerInterface $bulkMessageBodyHandler, int $priority = 0): BulkMessageBodyHandlerManager {
    $this->handlers[$priority][] = $bulkMessageBodyHandler;
    $this->sortedHandlers = NULL;
    return $this;
  }

  /**
   * Returns first enabled handler.
   */
  public function getHandler(): BulkMessageBodyHandlerInterface {
    if ($this->sortedHandlers === NULL) {
      $this->sortedHandlers = $this->sortHandlers();
    }
    if (empty($this->sortedHandlers)) {
      throw new \UnexpectedValueException('No bulk message sender has been found');
    }
    return reset($this->sortedHandlers);
  }

  /**
   * Sort handlers by priority key.
   */
  private function sortHandlers(): array {
    ksort($this->handlers);
    return array_merge(...$this->handlers);
  }

}
