<?php

declare(strict_types = 1);

namespace Drupal\sms_user;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Define a start and end date container for DrupalDateTime.
 *
 * This class is similar to \DatePeriod, but without an interval.
 */
class ActiveHoursDates {

  /**
   * The start date.
   *
   * @var \Drupal\Core\Datetime\DrupalDateTime
   */
  protected $start;

  /**
   * The end date.
   *
   * @var \Drupal\Core\Datetime\DrupalDateTime
   */
  protected $end;

  /**
   * Construct a new ActiveHoursDates object.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $start
   *   The start date.
   * @param \Drupal\Core\Datetime\DrupalDateTime $end
   *   The end date.
   */
  public function __construct(DrupalDateTime $start, DrupalDateTime $end) {
    $this->start = $start;
    $this->end = $end;
  }

  /**
   * Get the start date.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   The start date.
   */
  public function getStartDate() {
    return $this->start;
  }

  /**
   * Get the end date.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   The end date.
   */
  public function getEndDate() {
    return $this->end;
  }

}
