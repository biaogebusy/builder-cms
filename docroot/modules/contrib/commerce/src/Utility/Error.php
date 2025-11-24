<?php

namespace Drupal\commerce\Utility;

use Drupal\Core\Utility\Error as DrupalCoreError;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;

/**
 * Commerce error utility class.
 */
class Error {

  /**
   * Log a formatted exception message to the provided logger.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Throwable $exception
   *   The exception.
   * @param string $message
   *   (optional) The message.
   * @param array $additional_variables
   *   (optional) Any additional variables.
   * @param string $level
   *   The PSR log level. Must be valid constant in \Psr\Log\LogLevel.
   */
  public static function logException(LoggerInterface $logger, \Throwable $exception, string $message = DrupalCoreError::DEFAULT_ERROR_MESSAGE, array $additional_variables = [], string $level = LogLevel::ERROR): void {
    $logger->log($level, $message, DrupalCoreError::decodeException($exception) + $additional_variables);
  }

}
