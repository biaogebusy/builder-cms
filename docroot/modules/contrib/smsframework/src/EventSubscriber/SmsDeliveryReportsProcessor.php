<?php

declare(strict_types = 1);

namespace Drupal\sms\EventSubscriber;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\sms\Event\SmsDeliveryReportEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\sms\Event\SmsEvents;

/**
 * Handles delivery reports as they come in and updates storage.
 */
class SmsDeliveryReportsProcessor implements EventSubscriberInterface {

  /**
   * The entity storage for SMS delivery reports.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $reportStorage;

  /**
   * Creates a new SmsDeliveryReportsProcessor controller.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->reportStorage = $entity_type_manager->getStorage('sms_report');
  }

  /**
   * Updates the delivery status on stored SMS delivery reports.
   *
   * @param \Drupal\sms\Event\SmsDeliveryReportEvent $event
   *   The event containing updated delivery reports status.
   */
  public function updateDeliveryReports(SmsDeliveryReportEvent $event) {
    foreach ($event->getReports() as $report) {
      // Only messages that have message IDs can be tracked and updated.
      if ($report->getMessageId()) {
        $existing = $this->reportStorage->loadByProperties(['message_id' => $report->getMessageId()]);
        if ($existing) {
          $existing = reset($existing);
          $existing
            ->setStatus($report->getStatus())
            ->setStatusMessage($report->getStatusMessage())
            ->setStatusTime($report->getStatusTime())
            ->save();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Update delivery reports as they are received.
    $events[SmsEvents::DELIVERY_REPORT_POST_PROCESS][] = ['updateDeliveryReports', 1024];
    return $events;
  }

}
