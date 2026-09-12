<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Entity;

/**
 * Helper suggestion entity for dealing with notifications in rtc module.
 */
class RtcSuggestionNotificationEntity extends RtcNotificationEntityBase {

  public const ENTITY_TYPE_ID = 'ckeditor5_suggestion';

  /**
   * Chain id.
   *
   * @var string|null
   */
  private ?string $chainId = NULL;

  /**
   * The chain.
   *
   * @var array|null
   */
  private ?array $chain = NULL;

  /**
   * Is suggestion is head of chain.
   *
   * @var bool
   */
  private bool $isHeadOfChain = FALSE;

  /**
   * Is suggestion in chain.
   *
   * @var bool
   */
  private bool $isInChain = FALSE;

  /**
   * {@inheritDoc}
   */
  public function getEntityTypeId(): string {
    return self::ENTITY_TYPE_ID;
  }

  /**
   * Sets suggestion chain.
   *
   * @param array $chain
   *   The chain.
   *
   * @return RtcSuggestionNotificationEntity
   *   The current object.
   */
  public function setChain(array $chain): static {
    $this->chain = $chain;
    return $this;
  }

  /**
   * Returns the suggestion chain.
   *
   * @return array
   *   The suggestion chain.
   */
  public function getChain(): array {
    return $this->chain;
  }

  /**
   * Sets chain id.
   *
   * @param string $chain_id
   *   The chain id.
   *
   * @return RtcSuggestionNotificationEntity
   *   The current object.
   */
  public function setChainId(string $chain_id): static {
    $this->chainId = $chain_id;
    return $this;
  }

  /**
   * Returns chain id.
   *
   * @return string
   *   The chain id.
   */
  public function getChainId(): string {
    return $this->chainId;
  }

  /**
   * Sets is in chain value.
   *
   * @param bool $isInChain
   *   True if suggestion in chain.
   *
   * @return RtcSuggestionNotificationEntity
   *   The current object.
   */
  public function setIsInChain(bool $isInChain): static {
    $this->isInChain = $isInChain;
    return $this;
  }

  /**
   * Is suggestion in chain.
   *
   * @return bool
   *   True if suggestion is in chain.
   */
  public function isInChain(): bool {
    return $this->isInChain;
  }

  /**
   * Is suggestion is head of chain.
   *
   * @return bool
   *   True if suggestion is head of chain.
   */
  public function isHeadOfChain(): bool {
    return $this->isHeadOfChain;
  }

  /**
   * Sets is head if chain value.
   *
   * @param bool $isHeadOfChain
   *   True if suggestion is head of chain.
   *
   * @return RtcSuggestionNotificationEntity
   *   The current object.
   */
  public function setIsHeadOfChain(bool $isHeadOfChain): static {
    $this->isHeadOfChain = $isHeadOfChain;
    return $this;
  }

}
