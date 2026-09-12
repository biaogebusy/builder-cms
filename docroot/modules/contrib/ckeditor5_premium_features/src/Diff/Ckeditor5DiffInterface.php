<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\Diff;

/**
 * Interface for Ckeditor5 Diff class.
 */
interface Ckeditor5DiffInterface {

  /**
   * Processes two documents to find all changes between old and new document.
   *
   * @param string $oldDocument
   *   String representing previous version of a document.
   * @param string $newDocument
   *   String representing new version of a document.
   *
   * @return string|null
   *   Returns a string with all new content if anything was added to the
   *   new document.
   */
  public function getDiff(string $oldDocument, string $newDocument): ?string;

  /**
   * Returns a wider context presenting added parts with surrounding text.
   */
  public function getDiffAddedContext(): ?string;

  /**
   * Returns an array of all changes in the document.
   *
   * @param string $oldDocument
   *   String representing previous version of a document.
   * @param string $newDocument
   *   String representing new version of a document.
   *
   * @return array
   *   Returns an array representing all changes made to the document.
   */
  public function getDocumentChanges(string $oldDocument, string $newDocument): array;

  /**
   * Returns a wider context presenting all modified document parts.
   */
  public function getDiffContext(): ?string;

}
