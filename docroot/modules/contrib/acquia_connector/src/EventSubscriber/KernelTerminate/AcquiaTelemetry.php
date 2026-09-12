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

}
