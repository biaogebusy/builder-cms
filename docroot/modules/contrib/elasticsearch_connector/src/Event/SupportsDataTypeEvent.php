<?php

namespace Drupal\elasticsearch_connector\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event triggered when supported data types are determined.
 */
class SupportsDataTypeEvent extends Event {

  /**
   * Flag determining if data type is supported.
   *
   * @var bool
   */
  protected bool $isSupported = FALSE;

  /**
   * SupportsDataTypeEvent constructor.
   *
   * @param string $type
   *   The data type.
   */
  public function __construct(
    protected string $type,
  ) {
  }

  /**
   * Whether the data type is supported.
   */
  public function isSupported(): bool {
    return $this->isSupported;
  }

  /**
   * Sets whether the data type is supported.
   */
  public function setIsSupported(bool $isSupported): void {
    $this->isSupported = $isSupported;
  }

  /**
   * Get the data type.
   */
  public function getType(): string {
    return $this->type;
  }

}
