<?php

namespace Drupal\acquia_connector\EventSubscriber\KernelTerminate;

use Drupal\acquia_connector\Services\AcquiaTelemetryService as TelemetryService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Acquia Telemetry Event Subscriber.
 *
 * This event sends anonymized data to Acquia to help track modules and versions
 * Acquia sites use to ensure module updates don't break customer sites.
 *
 * @package Drupal\acquia_connector\EventSubscriber
 */
class AcquiaTelemetry implements EventSubscriberInterface {

  /**
   * Acquia Telemetry Service.
   *
   * @var \Drupal\acquia_connector\Services\AcquiaTelemetryService
   */
  private $telemetryService;

  /**
   * Constructs a telemetry object.
   *
   * @param \Drupal\acquia_connector\Services\AcquiaTelemetryService $acquia_telemetry
   *   The Acquia Telemetry Service.
   */
  public function __construct(TelemetryService $acquia_telemetry) {
    $this->telemetryService = $acquia_telemetry;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::TERMINATE][] = ['onTerminateEvent'];
    return $events;
  }

  /**
   * Sends Telemetry on a daily basis. This occurs after the response is sent.
   *
   * @param \Symfony\Component\HttpKernel\Event\KernelEvent $event
   *   The event.
   */
  public function onTerminateEvent(KernelEvent $event) {
    // Send Drupal Module Statistics events telemetry data.
    $this->telemetryService->sendTelemetry("Drupal Module Statistics");
  }

  /**
   * Creates and log event to dblog/syslog.
   *
   * @param string $event_type
   *   The event type.
   * @param array $event_properties
   *   (optional) Event properties.
   *
   * @throws \Exception
   *   Thrown if state key acquia_telemetry.loud is TRUE and request fails.
   *
   * @deprecated in acquia_connector:4.0.6 and is removed from
   *  acquia_connector:4.1.0 Use the
   *  'Drupal\acquia_connector\Services\AcquiaTelemetry::sendTelemetry'
   *  method instead.
   *
   * @see https://www.drupal.org/project/acquia_connector/issues/3421575
   */
  public function sendTelemetry(string $event_type, array $event_properties = []): void {
    $this->telemetryService->sendTelemetry($event_type, $event_properties);
  }

  /**
   * Gets an array of all Acquia Drupal extensions.
   *
   * @return array
   *   A flat array of all Acquia Drupal extensions.
   *
   * @deprecated in acquia_connector:4.0.6 and is removed from
   *  acquia_connector:4.1.0 Use the
   *  'Drupal\acquia_connector\Services\AcquiaTelemetry::getAcquiaExtensionNames'
   *  method instead.
   *
   * @see https://www.drupal.org/project/acquia_connector/issues/3421575
   */
  public function getAcquiaExtensionNames(): array {
    return $this->telemetryService->getAcquiaExtensionNames();
  }

}
