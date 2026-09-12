<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\EditorElement;

/**
 * Defines the suggestion item data model.
 */
class SuggestionItem {

  /**
   * The suggestion type.
   *
   * @var string|null
   */
  private ?string $type = NULL;

  /**
   * The suggestion ID.
   *
   * @var string|null
   */
  private ?string $suggestionId = NULL;

  /**
   * The suggestion user.
   *
   * @var mixed
   */
  private mixed $userId;

  /**
   * Creates the suggestion model instance.
   *
   * @param string $data
   *   The HTML element data.
   */
  public function __construct(string $data) {
    [$this->type, $this->suggestionId, $this->userId] = explode(':', $data);
  }

  /**
   * Gets the suggestion type.
   *
   * @return string
   *   The type, defaults to empty string.
   */
  public function getType(): string {
    return (string) $this->type;
  }

  /**
   * Gets the suggestion id.
   *
   * @return string
   *   The id, default to empty string.
   */
  public function getSuggestionId(): string {
    return (string) $this->suggestionId;
  }

  /**
   * Gets the user ID.
   *
   * @return int
   *   The user id, or 0.
   */
  public function getUserId(): int {
    return (int) $this->userId;
  }

}
