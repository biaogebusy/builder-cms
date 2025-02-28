<?php

namespace Drupal\commerce_order;

use Drupal\Component\Render\MarkupInterface;

/**
 * Provides a value object representing the "availability" of an order item.
 */
final class AvailabilityResult {

  /**
   * The availability result.
   *
   * @var bool|null
   */
  protected $result;

  /**
   * The availability result "reason".
   *
   * @var mixed|null
   */
  protected $reason;

  /**
   * The availability result "code".
   *
   * @var mixed|null
   */
  protected $code;

  /**
   * Constructs a new AvailabilityResult object.
   *
   * @param bool $result
   *   The availability result, FALSE when unavailable.
   * @param mixed|null $reason
   *   (optional) The reason why an order item is unavailable.
   * @param mixed|null $code
   *   (optional) The "code" explaining why an order item is unavailable.
   */
  public function __construct(bool $result, $reason = NULL, $code = NULL) {
    assert(is_string($reason) || is_null($reason) || $reason instanceof MarkupInterface);
    assert(is_string($code) || is_null($code) || $code instanceof MarkupInterface);
    $this->result = $result;
    $this->reason = $reason;
    $this->code = $code;
  }

  /**
   * Creates an availability result that is "neutral".
   *
   * @return static
   */
  public static function neutral() : AvailabilityResult {
    return new static(TRUE);
  }

  /**
   * Creates an availability result that is "unavailable".
   *
   * @param mixed|null $reason
   *   (optional) The reason why an order item is unavailable.
   * @param mixed|null $code
   *   (optional) The "code" explaining why an order item is unavailable.
   *
   * @return static
   */
  public static function unavailable($reason = NULL, $code = NULL) : AvailabilityResult {
    return new static(FALSE, $reason, $code);
  }

  /**
   * Gets the "code".
   *
   * @return mixed|null
   *   The "code" for this availability result, NULL when not provided.
   */
  public function getCode() {
    return $this->code;
  }

  /**
   * Gets the "reason".
   *
   * @return mixed|null
   *   The "reason" for this availability result, NULL when not provided.
   */
  public function getReason() {
    return $this->reason;
  }

  /**
   * Determines whether the availability result is "neutral".
   *
   * @return bool
   *   Whether the availability result is "neutral".
   */
  public function isNeutral(): bool {
    return $this->result === TRUE;
  }

  /**
   * Determines whether the availability result is "unavailable".
   *
   * @return bool
   *   Whether the availability is "unavailable".
   */
  public function isUnavailable(): bool {
    return $this->result === FALSE;
  }

}
