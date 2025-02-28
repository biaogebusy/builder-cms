<?php

declare(strict_types = 1);

namespace Drupal\sms\Provider;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
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
  protected $smsGatewayStorage;

  /**
   * SMS message entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $smsMessageStorage;

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The SMS provider.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  protected $smsProvider;

  /**
   * Time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Creates a new instance of SmsQueueProcessor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue service.
   * @param \Drupal\sms\Provider\SmsProviderInterface $sms_provider
   *   The SMS provider.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueueFactory $queue_factory, SmsProviderInterface $sms_provider, TimeInterface $time) {
    $this->smsGatewayStorage = $entity_type_manager->getStorage('sms_gateway');
    $this->smsMessageStorage = $entity_type_manager->getStorage('sms');
    $this->queue = $queue_factory->get(SmsProcessor::PLUGIN_ID, FALSE);
    $this->smsProvider = $sms_provider;
    $this->time = $time;
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
