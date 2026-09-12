<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features;

/**
 * Trait for formatting dates.
 */
trait CKeditorDateFormatterTrait {

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Formats time in the described format.
   *
   * @param int $time
   *   Time to be formatted.
   * @param string $format
   *   Format name.
   */
  public function format(int $time, string $format = 'medium'): string {
    if (!$this->dateFormatter) {
      $this->dateFormatter = \Drupal::service('date.formatter');
    }

    return $this->dateFormatter->format($time, $format);
  }

}
