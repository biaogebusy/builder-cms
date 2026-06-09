<?php

namespace Drupal\ai\DataTypeConverter;

use Drupal\ai\DataTypeConverter\AppliesResult\AppliesResultApplicable;
use Drupal\ai\DataTypeConverter\AppliesResult\AppliesResultInvalid;
use Drupal\ai\DataTypeConverter\AppliesResult\AppliesResultNotApplicable;

/**
 * Abstract class for applies result.
 *
 * This class provides a base implementation for the AppliesResultInterface.
 */
abstract class AppliesResult implements AppliesResultInterface {

  /**
   * The reason for the result.
   */
  protected string $reason = '';

  /**
   * Returns an applicable result.
   *
   * @return \Drupal\ai\DataTypeConverter\AppliesResultInterface
   *   The result.
   */
  public static function applicable(): AppliesResultInterface {
    return new AppliesResultApplicable();
  }

  /**
   * Returns a not applicable result.
   *
   * @param string $reason
   *   The reason for the result.
   *
   * @return \Drupal\ai\DataTypeConverter\AppliesResultInterface
   *   The result.
   */
  public static function notApplicable(string $reason): AppliesResultInterface {
    $result = new AppliesResultNotApplicable();
    $result->setReason($reason);
    return $result;
  }

  /**
   * Returns an invalid result.
   *
   * @param string $reason
   *   The reason for the result.
   *
   * @return \Drupal\ai\DataTypeConverter\AppliesResultInterface
   *   The result.
   */
  public static function invalid(string $reason): AppliesResultInterface {
    $result = new AppliesResultInvalid();
    $result->setReason($reason);
    return $result;
  }

  /**
   * Returns an invalid result with examples.
   *
   * @param array $examples
   *   The examples of valid values.
   * @param string|null $original_value
   *   The original value.
   * @param string|null $message
   *   The message.
   *
   * @return \Drupal\ai\DataTypeConverter\AppliesResultInterface
   *   The result.
   */
  public static function invalidWithExamples(array $examples, $original_value = NULL, $message = NULL): AppliesResultInterface {
    $result = new AppliesResultInvalid();
    $reason = 'Expected value format to match: ' . implode(' or ', $examples);
    if ($original_value) {
      $reason .= '. Received the following value instead: ' . $original_value;
    }
    if ($message) {
      $reason .= '. ' . $message;
    }
    $result->setReason($reason);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getReason(): string {
    return (string) $this->reason;
  }

  /**
   * {@inheritdoc}
   */
  public function setReason($reason): static {
    $this->reason = $reason;
    return $this;
  }

}
