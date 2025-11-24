<?php

declare(strict_types = 1);

namespace Drupal\sms\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\sms\Provider\SmsProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sms\Direction;

/**
 * Transmits SMS messages.
 *
 * @QueueWorker(
 *   id = \Drupal\sms\Plugin\QueueWorker\SmsProcessor::PLUGIN_ID,
 *   title = @Translation("SMS message processor"),
 *   cron = {"time" = 60}
 * )
 */
class SmsProcessor extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public const PLUGIN_ID = 'sms.messages';

  /**
   * SMS message entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $smsMessageStorage;

  /**
   * Constructs a new SmsProcessor object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\sms\Provider\SmsProviderInterface $smsProvider
   *   The SMS provider.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    protected SmsProviderInterface $smsProvider,
    protected TimeInterface $time,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->smsMessageStorage = $entity_type_manager->getStorage('sms');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('sms.provider'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (isset($data['id'])) {
      $id = $data['id'];
      /** @var \Drupal\sms\Entity\SmsMessageInterface $sms_message */
      if ($sms_message = $this->smsMessageStorage->load($id)) {
        switch ($sms_message->getDirection()) {
          case Direction::INCOMING:
            $this->smsProvider
              ->incoming($sms_message);
            break;

          case Direction::OUTGOING:
            $this->smsProvider
              ->send($sms_message);
            break;
        }

        $duration = NULL;
        if ($gateway = $sms_message->getGateway()) {
          $duration = $gateway->getRetentionDuration($sms_message->getDirection());
        }

        // Clean up SMS message now if retention is set to delete immediately.
        if ($duration === 0) {
          $sms_message->delete();
        }
        else {
          $sms_message
            ->setProcessedTime($this->time->getRequestTime())
            ->setQueued(FALSE)
            ->save();
        }
      }
    }
  }

}
