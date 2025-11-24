<?php

declare(strict_types = 1);

namespace Drupal\sms\Entity;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Url;
use Drupal\sms\Plugin\SmsGatewayPluginCollection;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\sms\Direction;

/**
 * Defines storage for an SMS Gateway instance.
 *
 * @ConfigEntityType(
 *   id = "sms_gateway",
 *   label = @Translation("SMS Gateway"),
 *   label_collection = @Translation("SMS Gateways"),
 *   label_singular = @Translation("SMS gateway"),
 *   label_plural = @Translation("SMS gateways"),
 *   label_count = @PluralTranslation(
 *     singular = "@count SMS gateway",
 *     plural = "@count SMS gateways",
 *   ),
 *   config_prefix = "gateway",
 *   admin_permission = "administer smsframework",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   handlers = {
 *     "list_builder" = "\Drupal\sms\Lists\SmsGatewayListBuilder",
 *     "form" = {
 *       "add" = "Drupal\sms\Form\SmsGatewayForm",
 *       "default" = "Drupal\sms\Form\SmsGatewayForm",
 *       "edit" = "Drupal\sms\Form\SmsGatewayForm",
 *       "delete" = "Drupal\sms\Form\SmsGatewayDeleteForm",
 *     }
 *   },
 *   links = {
 *     "canonical" = "/admin/config/smsframework/gateways/{sms_gateway}",
 *     "edit-form" = "/admin/config/smsframework/gateways/{sms_gateway}",
 *     "delete-form" = "/admin/config/smsframework/gateways/{sms_gateway}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "plugin",
 *     "settings",
 *     "skip_queue",
 *     "incoming_push_path",
 *     "reports_push_path",
 *     "retention_duration_incoming",
 *     "retention_duration_outgoing",
 *   },
 * )
 */
class SmsGateway extends ConfigEntityBase implements SmsGatewayInterface, EntityWithPluginCollectionInterface {

  /**
   * The ID of the SMS Gateway.
   *
   * @var string
   */
  protected $id;

  /**
   * The label of the SMS Gateway.
   *
   * @var string
   */
  protected $label;

  /**
   * The plugin instance settings.
   *
   * @var array
   *
   * Access settings using:
   * @code
   *   $gateway->getPlugin()->getConfiguration();
   * @endcode
   */
  protected $settings = [];

  /**
   * An SmsGateway plugin ID.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The plugin collection that holds the plugin for this entity.
   *
   * @var \Drupal\sms\Plugin\SmsGatewayPluginCollection
   */
  protected $pluginCollection;

  /**
   * Whether messages sent to this gateway should be sent immediately.
   *
   * @var bool
   */
  protected $skip_queue;

  /**
   * The internal path where incoming messages are received.
   *
   * @var string
   */
  protected $incoming_push_path;

  /**
   * The internal path where pushed delivery reports can be received.
   *
   * @var string
   */
  protected $reports_push_path;

  /**
   * How many seconds to hold messages after they are received.
   *
   * @var int
   */
  protected $retention_duration_incoming;

  /**
   * How many seconds to hold messages after they are sent.
   *
   * @var int
   */
  protected $retention_duration_outgoing;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    if (!isset($values['incoming_push_path'])) {
      $key = Crypt::randomBytesBase64(16);
      $values['incoming_push_path'] = '/sms/incoming/receive/' . $key;
    }
    if (!isset($values['reports_push_path'])) {
      $key = Crypt::randomBytesBase64(16);
      $values['reports_push_path'] = '/sms/delivery-report/receive/' . $key;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    /** @var static $original */
    $original = &$this->original;
    $original_path = isset($original) ? $original->getPushReportPath() : '';
    if ($original_path != $this->getPushReportPath()) {
      \Drupal::service('router.builder')->setRebuildNeeded();
    }
  }

  /**
   * Encapsulates the creation of the action's LazyPluginCollection.
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   *   The action's plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      $this->pluginCollection = new SmsGatewayPluginCollection(
        \Drupal::service('plugin.manager.sms_gateway'),
        $this->plugin,
        $this->settings,
      );
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return ['settings' => $this->getPluginCollection()];
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    return $this->getPluginCollection()->get($this->plugin);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getSkipQueue() {
    return !empty($this->skip_queue);
  }

  /**
   * {@inheritdoc}
   */
  public function setSkipQueue($skip_queue) {
    $this->skip_queue = (boolean) $skip_queue;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPushIncomingPath() {
    return $this->incoming_push_path;
  }

  /**
   * {@inheritdoc}
   */
  public function setPushIncomingPath($path) {
    $this->incoming_push_path = $path;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPushReportUrl() {
    return Url::fromRoute('sms.delivery_report.receive.' . $this->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getPushReportPath() {
    return $this->reports_push_path;
  }

  /**
   * {@inheritdoc}
   */
  public function setPushReportPath($path) {
    $this->reports_push_path = $path;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRetentionDuration($direction) {
    return match ($direction) {
      Direction::INCOMING => (int) $this->retention_duration_incoming,
      Direction::OUTGOING => (int) $this->retention_duration_outgoing,
      default => throw new \InvalidArgumentException(sprintf('%s is not a valid direction.', $direction)),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function setRetentionDuration($direction, $retention_duration) {
    switch ($direction) {
      case Direction::INCOMING:
        $this->retention_duration_incoming = $retention_duration;
        break;

      case Direction::OUTGOING:
        $this->retention_duration_outgoing = $retention_duration;
        break;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxRecipientsOutgoing() {
    $definition = $this->getPlugin()
      ->getPluginDefinition();
    return isset($definition['outgoing_message_max_recipients']) ? (int) $definition['outgoing_message_max_recipients'] : 1;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsIncoming() {
    $definition = $this->getPlugin()
      ->getPluginDefinition();
    return isset($definition['incoming']) ? (boolean) $definition['incoming'] : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function autoCreateIncomingRoute() {
    $definition = $this->getPlugin()
      ->getPluginDefinition();
    return isset($definition['incoming_route']) ? (boolean) $definition['incoming_route'] : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isScheduleAware() {
    $definition = $this->getPlugin()
      ->getPluginDefinition();
    return !empty($definition['schedule_aware']);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsReportsPull() {
    $definition = $this->getPlugin()
      ->getPluginDefinition();
    return isset($definition['reports_pull']) ? (boolean) $definition['reports_pull'] : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsReportsPush() {
    $definition = $this->getPlugin()
      ->getPluginDefinition();
    return isset($definition['reports_push']) ? (boolean) $definition['reports_push'] : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsCreditBalanceQuery() {
    $definition = $this->getPlugin()
      ->getPluginDefinition();
    return isset($definition['credit_balance_available']) ? (boolean) $definition['credit_balance_available'] : FALSE;
  }

}
