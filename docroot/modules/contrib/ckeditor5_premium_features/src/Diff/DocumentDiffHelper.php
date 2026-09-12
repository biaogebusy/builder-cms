<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Diff;

use Drupal\ckeditor5_premium_features\Plugin\Filter\FilterCollaboration;
use Drupal\filter\FilterPluginManager;

/**
 * This class checks if document is changed.
 */
class DocumentDiffHelper {

  /**
   * Collaboration filter.
   *
   * @var \Drupal\ckeditor5_premium_features\Plugin\Filter\FilterCollaboration
   */
  protected FilterCollaboration $filterCollaboration;

  /**
   * Constructor.
   *
   * @param \Drupal\ckeditor5_premium_features\Diff\Ckeditor5DiffInterface $ckeditor5Diff
   *   Ckeditor5 diff service.
   * @param \Drupal\filter\FilterPluginManager $filterPluginManager
   *   Filter plugin manager.
   */
  public function __construct(
    protected Ckeditor5DiffInterface $ckeditor5Diff,
    FilterPluginManager $filterPluginManager,
  ) {
    $this->filterCollaboration = $filterPluginManager->createInstance('ckeditor5_premium_features_collaboration_filter');
  }

  /**
   * Check if the document without collaboration tags changed.
   *
   * @param string $originalData
   *   Original document data.
   * @param string $newData
   *   New document data.
   * @param array $trackChangesData
   *   Track changes data.
   *
   * @return bool
   *   Is document changed.
   */
  public function isRawDocumentChanged(string $originalData, string $newData, array $trackChangesData): bool {
    $originalDataWithoutCollaborationTags = $originalData ? $this->filterCollaboration->processWithTrackChangesData($originalData, $trackChangesData)->getProcessedText() : '';
    $originalNewDataWithoutCollaborationTags = $this->filterCollaboration->processWithTrackChangesData($newData, $trackChangesData)->getProcessedText();

    // Check if a raw document without the collaboration tags is changed.
    $this->ckeditor5Diff->getDiff($originalDataWithoutCollaborationTags, $originalNewDataWithoutCollaborationTags);
    if ($this->ckeditor5Diff->getDiffContext()) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Returns an array of all changes in the document.
   *
   * @param string $originalData
   *   The original document.
   * @param string $newData
   *   The updated document.
   *
   * @return array
   *   Returns an array representing all changes made to the document.
   */
  public function getDocumentChanges(string $originalData, string $newData) {
    $htmlHelper = $this->filterCollaboration->getHtmlHelper();

    // Replace collaboration attributes with tags in parameters.
    $originalData = $htmlHelper->convertCollaborationAttributesInString($originalData);
    $newData = $htmlHelper->convertCollaborationAttributesInString($newData);

    return $this->ckeditor5Diff->getDocumentChanges($originalData, $newData);
  }

}
