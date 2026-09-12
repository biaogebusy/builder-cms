<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features;

use Drupal\Core\Logger\LoggerChannelTrait;
use Exception;

/**
 * Trait providing error logging helper methods.
 */
trait CKeditorPremiumLoggerChannelTrait {
  use LoggerChannelTrait;

  /**
   * Log error message.
   *
   * @param string $message
   *   Message.
   * @param array $prams
   *   Parameters.
   */
  protected function error(string $message, array $prams): void {
    $this->getLogger(self::getLoggerName())->error($message, $prams);
  }

  /**
   * Logs an error message along with details about passed Exception.
   *
   * @param string $message
   *   Shor message describing source of an exception.
   * @param Exception $e
   *   Exception to be logged.
   */
  protected function logException(string $message, Exception $e): void {
    $this->error($message . "<br /> @error <br /> <br /><pre>@trace</pre>", [
      '@error' => $e->getMessage(),
      '@trace' => $e->getTraceAsString(),
    ]);
  }

  /**
   * Returns the logger name.
   */
  public static function getLoggerName(): string {
    return 'ckeditor5_premium_features';
  }

}
