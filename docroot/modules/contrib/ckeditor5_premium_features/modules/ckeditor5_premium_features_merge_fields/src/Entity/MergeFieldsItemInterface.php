<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_merge_fields\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Merge Fields Item entities.
 */
interface MergeFieldsItemInterface extends ConfigEntityInterface {

  /**
   * Gets the Merge Fields Item token.
   *
   * @return string
   *   The Merge Fields Item token.
   */
  public function getToken(): string;

  /**
   * Sets the Merge Fields Item token.
   *
   * @param string $token
   *   The Merge Fields Item token.
   *
   * @return \Drupal\ckeditor5_premium_features_merge_fields\Entity\MergeFieldsItemInterface
   *   The called Merge Fields Item entity.
   */
  public function setToken(string $token): MergeFieldsItemInterface;

  /**
   * Gets the Merge Fields Group ID.
   *
   * @return string
   *   The Merge Fields Group ID.
   */
  public function getGroup(): string;

  /**
   * Sets the Merge Fields Group ID.
   *
   * @param string $group
   *   The Merge Fields Group ID.
   *
   * @return \Drupal\ckeditor5_premium_features_merge_fields\Entity\MergeFieldsItemInterface
   *   The called Merge Fields Item entity.
   */
  public function setGroup(string $group): MergeFieldsItemInterface;

  /**
   * Gets the Merge Fields Item weight.
   *
   * @return int
   *   The Merge Fields Item weight.
   */
  public function getWeight(): int;

  /**
   * Sets the Merge Fields Item weight.
   *
   * @param int $weight
   *   The Merge Fields Item weight.
   *
   * @return \Drupal\ckeditor5_premium_features_merge_fields\Entity\MergeFieldsItemInterface
   *   The called Merge Fields Item entity.
   */
  public function setWeight(int $weight): MergeFieldsItemInterface;

  /**
   * Gets the Merge Fields Item type.
   *
   * @return string
   *   The Merge Fields Item type.
   */
  public function getType(): string;

  /**
   * Sets the Merge Fields Item type.
   *
   * @param string $type
   *   The Merge Fields Item type.
   *
   * @return \Drupal\ckeditor5_premium_features_merge_fields\Entity\MergeFieldsItemInterface
   *   The called Merge Fields Item entity.
   */
  public function setType(string $type): MergeFieldsItemInterface;

  /**
   * Gets the Merge Fields Item default value.
   *
   * @return string
   *   The Merge Fields Item default value.
   */
  public function getDefaultValue(): string;

  /**
   * Sets the Merge Fields Item default value.
   *
   * @param string $default_value
   *   The Merge Fields Item default value.
   *
   * @return \Drupal\ckeditor5_premium_features_merge_fields\Entity\MergeFieldsItemInterface
   *   The called Merge Fields Item entity.
   */
  public function setDefaultValue(string $default_value): MergeFieldsItemInterface;

  /**
   * Gets the Merge Fields Item height.
   *
   * @return int|null
   *   The Merge Fields Item height.
   */
  public function getHeight(): ?int;

  /**
   * Sets the Merge Fields Item height.
   *
   * @param int|null $height
   *   The Merge Fields Item height.
   *
   * @return \Drupal\ckeditor5_premium_features_merge_fields\Entity\MergeFieldsItemInterface
   *   The called Merge Fields Item entity.
   */
  public function setHeight(?int $height): MergeFieldsItemInterface;

  /**
   * Gets the Merge Fields Item width.
   *
   * @return int|null
   *   The Merge Fields Item width.
   */
  public function getWidth(): ?int;

  /**
   * Sets the Merge Fields Item width.
   *
   * @param int|null $width
   *   The Merge Fields Item width.
   *
   * @return \Drupal\ckeditor5_premium_features_merge_fields\Entity\MergeFieldsItemInterface
   *   The called Merge Fields Item entity.
   */
  public function setWidth(?int $width): MergeFieldsItemInterface;
}
