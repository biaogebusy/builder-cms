<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Utility;

/**
 * Helper class for handling document data.
 */
class NotificationDocumentHelper {

  /**
   * Documemnt element id.
   *
   * @var string
   */
  private string $elementId;

  /**
   * Original content of the document.
   *
   * @var string|null
   */
  private ?string $originalData;

  /**
   * New data of the document.
   *
   * @var string|null
   */
  private ?string $newData;

  /**
   * NotificationDocumentHelper constructor.
   *
   * @param string|null $elementId
   * @param string|null $originalData
   * @param string|null $newData
   */
  public function __construct(string $elementId, ?string $originalData, ?string $newData) {
    $this->elementId = $elementId;
    $this->originalData = $originalData;
    $this->newData = $newData;
  }

  /**
   * Returns the documemnt element id.
   *
   * @return string|null
   */
  public function getElementId():string {
    return $this->elementId;
  }

  /**
   * Returns the document original content data.
   *
   * @return string|null
   */
  public function getOriginalData():?string {
    return $this->originalData;
  }

  /**
   * Returns the document new content data.
   *
   * @return string|null
   */
  public function getNewData():?string {
    return $this->newData;
  }

}
