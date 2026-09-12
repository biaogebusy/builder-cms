<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides the interface for the CKEditor5 "Suggestion" entity.
 */
interface SuggestionInterface extends ContentEntityInterface {

  public const ENTITY_TYPE_ID = 'ckeditor5_suggestion';
  public const SUGGESTION_REJECTED = 'discard';
  public const SUGGESTION_ACCEPTED = 'accept';

  /**
   * Gets the suggestion type.
   *
   * @return string
   *   The type, defaults to empty string.
   */
  public function getType(): string;

  /**
   * Sets the suggestion type.
   *
   * @param string $type
   *   The type to be set.
   */
  public function setType(string $type): static;

  /**
   * Gets the JSON suggestion data.
   *
   * @param bool $raw
   *   FALSE to return decoded, TRUE for having
   *   the raw string value.
   *
   * @return string|array
   *   The data decoded or raw.
   */
  public function getData(bool $raw = FALSE): string|array;

  /**
   * Sets the data value.
   *
   * @param array|string $data
   *   The data value (decoded or raw).
   */
  public function setData(array|string $data): static;

  /**
   * Sets the comment state (if has comments).
   *
   * @param bool $state
   *   The state value.
   */
  public function setCommentState(bool $state): static;

  /**
   * Gets the has_comments flag value.
   *
   * @return bool
   *   TRUE if it has comments, FALSE otherwise.
   */
  public function hasComments(): bool;

  /**
   * Returns chain_id value.
   */
  public function getChainId(): string;

  /**
   * Sets chain_id value.
   *
   * @param string|null $chain_id
   *   Chain ID or NULL if not in chain.
   */
  public function setChainId(?string $chain_id = NULL): static;

  /**
   * Checks if the entity is part of the suggestion chain.
   */
  public function isInChain(): bool;

  /**
   * Checks if the entity is head of suggestion chain.
   */
  public function isHeadOfChain(): bool;

  /**
   * Returns a list of chained suggestions.
   *
   * @return \Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionInterface[]
   *   List of suggestions matching the same chain_id.
   */
  public function getChain(): array;

  /**
   * Returns suggestion status if was approved or rejected, otherwise NULL.
   *
   * @return string|null
   *   One of: SuggestionInterface::SUGGESTION_REJECTED,
   *   SuggestionInterface::SUGGESTION_ACCEPTED or NULL.
   */
  public function getStatus(): string|NULL;

  /**
   * Returns comment thread that replied to the current entity.
   */
  public function getThread(): array;

}
