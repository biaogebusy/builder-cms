<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\EditorElement;

/**
 * Defines the comment item data model.
 */
class CommentItem {

  /**
   * The comment thread ID.
   *
   * @var string
   */
  private string $threadId;

  /**
   * Creates the suggestion model instance.
   *
   * @param string $data
   *   The HTML element data.
   */
  public function __construct(string $data) {
    [$this->threadId] = explode(':', $data);
  }

  /**
   * Gets the comment thread ID.
   *
   * @return string
   *   The comment thread ID.
   */
  public function getThreadId(): string {
    return $this->threadId;
  }

}
