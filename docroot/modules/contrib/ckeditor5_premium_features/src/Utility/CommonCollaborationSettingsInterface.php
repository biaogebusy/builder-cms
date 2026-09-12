<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\Utility;

/**
 * Interface describing common collaboration settings methods.
 */
interface CommonCollaborationSettingsInterface {

  /**
   * Returns annotation sidebar type config.
   */
  public function getAnnotationSidebarType(): string;

  /**
   * Check if sidebar items should be prevented from scrolling out of view.
   */
  public function isScrollingAnnotationsOutOfViewForbidden(): bool;

}
