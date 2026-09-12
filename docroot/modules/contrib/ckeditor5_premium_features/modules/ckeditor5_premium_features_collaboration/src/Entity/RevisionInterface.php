<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

/**
 * Defines the interface for the revision entities.
 */
interface RevisionInterface {

  public const ENTITY_TYPE_ID = 'ckeditor5_revision';

  /**
   * Gets the revision name.
   *
   * @return string|null
   *   The name.
   */
  public function getName(): ?string;

  /**
   * Sets the revision name.
   *
   * @param string|null $name
   *   The name.
   */
  public function setName(?string $name): static;

  /**
   * Gets the revision authors IDs.
   *
   * @return array
   *   The IDs of the authors.
   */
  public function getAuthors(): array;

  /**
   * Sets the revision authors IDs.
   *
   * @param array $ids
   *   The IDs of the authors.
   */
  public function setAuthors(array $ids): static;

  /**
   * Gets the diff data of the revision.
   *
   * @return array
   *   The data.
   */
  public function getDiffData(): array;

  /**
   * Sets the diff data value.
   *
   * @param array|string $data
   *   The data value (decoded or raw).
   */
  public function setDiffData(array|string $data): static;

  /**
   * Gets the current version number.
   *
   * @return int
   *   The version number.
   */
  public function getCurrentVersion(): int;

  /**
   * Sets the current revision version.
   *
   * @param int $version
   *   The revision number.
   */
  public function setCurrentVersion(int $version): static;

  /**
   * Gets the previous version number.
   *
   * @return int
   *   The version number.
   */
  public function getPreviousVersion(): int;

  /**
   * Sets the current revision version.
   *
   * @param int $version
   *   The revision number.
   */
  public function setPreviousVersion(int $version): static;

}
