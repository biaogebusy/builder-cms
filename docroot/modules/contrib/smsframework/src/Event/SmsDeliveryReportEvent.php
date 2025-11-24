<?php

declare(strict_types = 1);

namespace Drupal\sms\Event;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Handle a received delivery report.
 *
 * @see \Drupal\sms\Event\SmsEvents
 */
class SmsDeliveryReportEvent extends Event {

  /**
   * The response to receiving a pushed delivery report.
   *
   * @var \Symfony\Component\HttpFoundation\Response
   */
  protected Response $response;

  /**
   * The SMS delivery reports.
   *
   * @var \Drupal\sms\Message\SmsDeliveryReportInterface[]
   */
  protected array $reports;

  /**
   * Get the response for this event.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response for this event.
   */
  public function getResponse() {
    return $this->response;
  }

  /**
   * Set the response on this event.
   *
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The response to set for this event.
   *
   * @return $this
   *   Returns this event for chaining.
   */
  public function setResponse(Response $response) {
    $this->response = $response;
    return $this;
  }

  /**
   * Get all delivery reports on this event.
   *
   * @return \Drupal\sms\Message\SmsDeliveryReportInterface[]
   *   The delivery reports on this event.
   */
  public function getReports() {
    return $this->reports;
  }

  /**
   * Set the delivery reports on this event.
   *
   * @param \Drupal\sms\Message\SmsDeliveryReportInterface[] $reports
   *   The delivery reports to set on this event.
   *
   * @return $this
   *   Returns this event for chaining.
   */
  public function setReports(array $reports) {
    $this->reports = $reports;
    return $this;
  }

}
