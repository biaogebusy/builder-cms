<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_cloud_services\Entity;

/**
 * Channel entity interface.
 */
interface ChannelInterface {
  public const ENTITY_TYPE_ID = 'ckeditor5_channel';

  /**
   * Getter for channel key ID.
   */
  public function getKeyId(): ?string;

  /**
   * Setter for key ID property.
   *
   * @param string $value
   *   Key ID value.
   */
  public function setKeyId(string $value): static;

  /**
   * Getter for target entity UUID.
   */
  public function getTargetEntityUuid(): ?string;

  /**
   * Getter for target entity type.
   */
  public function getTargetEntityType(): ?string;

}
