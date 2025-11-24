<?php

declare(strict_types = 1);

namespace Drupal\sms\Provider;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\sms\Direction;
use Drupal\sms\Plugin\QueueWorker\SmsProcessor;

/**
 * The SMS Queue Processor.
 */
class SmsQueueProcessor implements SmsQueueProcessorInterface {

  /**
   * SMS gateway config storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $smsGatewayStorage;

  /**
   * SMS message entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $smsMessageStorage;

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected QueueInterface $queue;

  /**
   * Creates a new instance of SmsQueueProcessor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Queue\QueueFactory $queueQactory
   *   The queue service.
   * @param \Drupal\sms\Provider\SmsProviderInterface $smsProvider
   *   The SMS provider.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    QueueFactory $queueQactory,
    protected SmsProviderInterface $smsProvider,
    protected TimeInterface $time,
  ) {
    $this->smsGatewayStorage = $entityTypeManager->getStorage('sms_gateway');
    $this->smsMessageStorage = $entityTypeManager->getStorage('sms');
    $this->queue = $queueQactory->get(SmsProcessor::PLUGIN_ID, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function processUnqueued() {
    /** @var \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway */
    $ids = [];
    foreach ($this->smsGatewayStorage->loadMultiple() as $sms_gateway) {
      $query = $this->smsMessageStorage
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('gateway', $sms_gateway->id(), '=')
        ->condition('queued', 0, '=')
        ->condition('processed', NULL, 'IS NULL');

      if (!$sms_gateway->isScheduleAware()) {
        $query->condition('send_on', $this->time->getRequestTime(), '<=');
      }

      $ids += $query->execute();
    }

    /** @var \Drupal\sms\Entity\SmsMessageInterface $sms_message */
    foreach ($this->smsMessageStorage->loadMultiple($ids) as $sms_message) {
      $data = ['id' => $sms_message->id()];
      if ($this->queue->createItem($data)) {
        $sms_message
          ->setQueued(TRUE)
          ->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    $directions = [
      Direction::INCOMING,
      Direction::OUTGOING,
    ];

    $ids = [];
    /** @var \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway */
    foreach ($this->smsGatewayStorage->loadMultiple() as $sms_gateway) {
      foreach ($directions as $direction) {
        $lifetime = $sms_gateway->getRetentionDuration($direction);
        if ($lifetime !== -1) {
          $ids += $this->smsMessageStorage
            ->getQuery()
            ->accessCheck(FALSE)
            ->condition('gateway', $sms_gateway->id(), '=')
            ->condition('queued', 0)
            ->condition('direction', $direction)
            ->condition('processed', NULL, 'IS NOT NULL')
            ->condition('processed', $this->time->getRequestTime() - $lifetime, '<=')
            ->execute();
        }
      }
    }

    if ($ids) {
      $this->smsMessageStorage->delete($this->smsMessageStorage->loadMultiple($ids));
    }
  }

}
