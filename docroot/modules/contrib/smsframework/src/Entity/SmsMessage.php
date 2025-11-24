<?php

declare(strict_types = 1);

namespace Drupal\sms\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityInterface;
use Drupal\sms\Exception\SmsStorageException;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\sms\Message\SmsMessageInterface as StdSmsMessageInterface;
use Drupal\sms\Message\SmsMessageResultInterface as StdMessageResultInterface;

/**
 * Defines the SMS message entity.
 *
 * The SMS message entity is used to maintain the message while it is queued to
 * send to the gateway. After the message has been sent, the message may persist
 * in the database as an archive record.
 *
 * @ContentEntityType(
 *   id = "sms",
 *   label = @Translation("SMS Message"),
 *   label_collection = @Translation("SMS Messages"),
 *   label_singular = @Translation("SMS message"),
 *   label_plural = @Translation("SMS messages"),
 *   label_count = @PluralTranslation(
 *     singular = "@count SMS message",
 *     plural = "@count SMS messages",
 *   ),
 *   base_table = "sms",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   handlers = {
 *     "views_data" = "Drupal\sms\Views\SmsMessageViewsData",
 *   },
 * )
 */
class SmsMessage extends ContentEntityBase implements SmsMessageInterface {

  /**
   * Temporarily stores the message result until save().
   *
   * @var \Drupal\sms\Message\SmsMessageResultInterface|null
   */
  protected ?StdMessageResultInterface $result = NULL;

  /**
   * Following are implementors of plain SmsMessage interface.
   *
   * @see \Drupal\sms\Entity\SmsMessageInterface
   */

  /**
   * {@inheritdoc}
   */
  public function getRecipients() {
    $recipients = [];
    foreach ($this->get('recipient_phone_number') as $recipient) {
      $recipients[] = $recipient->value;
    }
    return $recipients;
  }

  /**
   * {@inheritdoc}
   */
  public function addRecipient($recipient) {
    // Ensure duplicate recipients cannot be added.
    foreach ($this->recipient_phone_number as $item) {
      if ($item->value == $recipient) {
        return $this;
      }
    }
    $this->recipient_phone_number->appendItem($recipient);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addRecipients(array $recipients) {
    foreach ($recipients as $recipient) {
      $this->addRecipient($recipient);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeRecipient($recipient) {
    $this->recipient_phone_number->filter(static function ($item) use ($recipient) {
      return ($item->value != $recipient);
    });
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeRecipients(array $recipients) {
    $this->recipient_phone_number->filter(static function ($item) use ($recipients) {
      return !in_array($item->value, $recipients);
    });
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    return ($first = $this->get('options')->first()) ? $first->getValue() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOption($name) {
    $options = $this->getOptions();
    return $options[$name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setOption($name, $value) {
    $options = $this->getOptions();
    $options[$name] = $value;
    $this->set('options', $options);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeOption($name) {
    $options = $this->getOptions();
    unset($options[$name]);
    $this->set('options', $options);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getResult() {
    // Check the temporary store first as that contains the most recent value.
    // Also, if the entity is new then return that value (can be null).
    if ($this->result || $this->isNew()) {
      return $this->result;
    }
    $results = $this->entityTypeManager()
      ->getStorage('sms_result')
      ->loadByProperties(['sms_message' => $this->id()]);
    return $results ? reset($results) : NULL;
  }

  /**
   * Sets the result associated with this SMS message.
   *
   * Results on a saved SMS message are immutable and cannot be changed. An
   * exception will be thrown if this method is called on an SmsMessage that
   * already has saved results.
   *
   * @param \Drupal\sms\Message\SmsMessageResultInterface|null $result
   *   The result to associate with this SMS message, or NULL if there is no
   *   result.
   *
   * @return $this
   *   The called SMS message object.
   *
   * @throws \Drupal\sms\Exception\SmsStorageException
   *   If the SMS message entity already has saved results.
   */
  public function setResult(?StdMessageResultInterface $result = NULL) {
    // Throw an exception if there is already a result for this SMS message.
    $previous_result = $this->getResult();
    if ($previous_result) {
      throw new SmsStorageException('Saved SMS message results cannot be changed or updated.');
    }
    elseif ($result) {
      // Temporarily store the result so it can be retrieved without having to
      // save the message entity.
      $this->result = $result;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getReport($recipient) {
    // If a result has been set, check that first.
    if ($this->result) {
      return $this->result->getReport($recipient);
    }
    elseif (!$this->isNew()) {
      $reports = $this->entityTypeManager()
        ->getStorage('sms_report')
        ->loadByProperties([
          'sms_message' => $this->id(),
          'recipient' => $recipient,
        ]);
      return $reports ? reset($reports) : NULL;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getReports() {
    // If a result has been set, check that first.
    if ($this->result) {
      return $this->result->getReports();
    }
    elseif (!$this->isNew()) {
      return array_values($this->entityTypeManager()
        ->getStorage('sms_report')
        ->loadByProperties(['sms_message' => $this->id()]));
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSender() {
    $sender_name = $this->get('sender_name');
    if (isset($sender_name->value)) {
      return $sender_name->value;
    }
    else {
      return ($sender_entity = $this->getSenderEntity()) ? $sender_entity->label() : NULL;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param string|null $sender
   *   The name of the sender. Or NULL to defer to the label of the sender
   *   entity.
   *
   * @see ::getSender()
   */
  public function setSender($sender) {
    $this->set('sender_name', $sender);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage() {
    return $this->get('message')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage($message) {
    $this->set('message', $message);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    return $this->get('uuid')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getUid() {
    $sender = $this->getSenderEntity();
    return ($sender instanceof UserInterface) ? $sender->id() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setUid($uid) {
    $this->setSenderEntity(User::load($uid));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isAutomated() {
    return (bool) $this->get('automated')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setAutomated($automated) {
    $this->set('automated', $automated);
    return $this;
  }

  /**
   * Following are implementors of entity interface.
   *
   * @see \Drupal\sms\Entity\SmsMessageInterface
   */

  /**
   * {@inheritdoc}
   */
  public function getDirection() {
    return $this->get('direction')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDirection($direction) {
    $this->set('direction', $direction);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGateway() {
    return $this->get('gateway')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setGateway(SmsGatewayInterface $gateway) {
    $this->set('gateway', $gateway);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSenderNumber() {
    return $this->get('sender_phone_number')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSenderNumber($number) {
    $this->set('sender_phone_number', $number);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSenderEntity() {
    return $this->get('sender_entity')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setSenderEntity(EntityInterface $entity) {
    $this->set('sender_entity', $entity);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientEntity() {
    return $this->get('recipient_entity')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setRecipientEntity(EntityInterface $entity) {
    $this->set('recipient_entity', $entity);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isQueued() {
    return (bool) $this->get('queued')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setQueued($is_queued) {
    $this->set('queued', $is_queued);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSendTime() {
    return $this->get('send_on')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSendTime($send_time) {
    $this->set('send_on', $send_time);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessedTime() {
    return $this->get('processed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setProcessedTime($processed) {
    $this->set('processed', $processed);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function chunkByRecipients($size) {
    $recipients_all = $this->getRecipients();

    // Save processing by returning early.
    if ($size < 1 || count($recipients_all) <= $size) {
      return [$this];
    }

    // Create a baseline SMS message with recipients cleaned out.
    $base = $this->createDuplicate();
    $base->removeRecipients($recipients_all);

    $messages = [];
    foreach (array_chunk($recipients_all, $size) as $recipients) {
      $messages[] = $base->createDuplicate()
        ->addRecipients($recipients);
    }
    return $messages;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    // Identifiers.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('SMS message ID'))
      ->setDescription(t('The SMS message ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The SMS message UUID.'))
      ->setReadOnly(TRUE);

    $fields['gateway'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Gateway Plugin'))
      ->setDescription(t('The gateway plugin instance.'))
      ->setSetting('target_type', 'sms_gateway')
      ->setReadOnly(TRUE)
      ->setRequired(TRUE);

    $fields['direction'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Transmission direction'))
      ->setDescription(t('Transmission direction, See SmsMessageInterface::DIRECTION_*.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', FALSE)
      ->setSetting('size', 'tiny')
      ->setRequired(TRUE);

    // Sender and receivers.
    $fields['sender_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Sender name'))
      ->setDescription(t('The name of the sender.'))
      ->setRequired(FALSE);

    $fields['sender_phone_number'] = BaseFieldDefinition::create('telephone')
      ->setLabel(t('Sender phone number'))
      ->setDescription(t('The phone number of the sender.'))
      ->setDefaultValue('')
      ->setRequired(FALSE);

    $fields['sender_entity'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel(t('Sender entity'))
      ->setDescription(t('The entity who sent the SMS message.'))
      ->setRequired(FALSE);

    $fields['recipient_phone_number'] = BaseFieldDefinition::create('telephone')
      ->setLabel(t('Recipient phone number'))
      ->setDescription(t('The phone number of the recipient.'))
      ->setRequired(FALSE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED);

    $fields['recipient_entity'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel(t('Recipient entity'))
      ->setDescription(t('The entity who received the SMS message.'))
      ->setRequired(FALSE);

    // Meta information.
    $fields['options'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Options'))
      ->setDescription(t('Options to pass to the gateway.'));

    $fields['automated'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Is automated'))
      ->setDescription(t('Whether this SMS message was generated automatically. 0=generated by user action, 1=generated automatically.'))
      ->setDefaultValue(TRUE)
      ->setRequired(TRUE)
      ->setSetting('on_label', t('Automated'))
      ->setSetting('off_label', t('Not automated'));

    $fields['queued'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Queued'))
      ->setDescription(t('Whether the SMS message is in the queue to be processed.'))
      ->setDefaultValue(FALSE)
      ->setRequired(TRUE)
      ->setSetting('on_label', t('Queued'))
      // Off = processed, or not queued yet.
      ->setSetting('off_label', t('Not queued'));

    // Dates.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Creation date'))
      ->setDescription(t('The time the SMS message was created.'))
      ->setRequired(TRUE);

    $fields['send_on'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Send date'))
      ->setDescription(t('The time to send the SMS message.'))
      ->setRequired(TRUE);

    $fields['processed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Processed'))
      ->setDescription(t('The time the SMS message was processed. This value does not indicate whether the message was sent, only that the gateway accepted the request.'))
      ->setRequired(FALSE);

    // Message contents.
    $fields['message'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Message'))
      ->setDescription(t('The SMS message.'))
      ->setDefaultValue('')
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * Converts a standard SMS message object to a SMS message entity.
   *
   * @param \Drupal\sms\Message\SmsMessageInterface $sms_message
   *   A standard SMS message.
   *
   * @return static
   *   An unsaved SMS Message entity.
   */
  public static function convertFromSmsMessage(StdSmsMessageInterface $sms_message) {
    if ($sms_message instanceof static) {
      return $sms_message;
    }

    $new = static::create();
    $new
      ->setDirection($sms_message->getDirection())
      ->setAutomated($sms_message->isAutomated())
      ->setSender($sms_message->getSender())
      ->setSenderNumber($sms_message->getSenderNumber())
      ->addRecipients($sms_message->getRecipients())
      ->setMessage($sms_message->getMessage())
      ->setResult($sms_message->getResult());

    if ($gateway = $sms_message->getGateway()) {
      $new->setGateway($gateway);
    }

    if ($uid = $sms_message->getUid()) {
      $new->setUid($uid);
    }

    foreach ($sms_message->getOptions() as $k => $v) {
      $new->setOption($k, $v);
    }

    return $new;
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);
    $results = [];
    $reports = [];
    /** @var \Drupal\sms\Entity\SmsMessageInterface $sms_message */
    foreach ($entities as $sms_message) {
      // Since the $sms_message can have both in-memory and stored objects, only
      // need to delete actual stored entities.
      if (($result = $sms_message->getResult()) && $result instanceof EntityInterface) {
        $results[] = $result;
      }
      foreach ($sms_message->getReports() as $report) {
        if ($report instanceof EntityInterface) {
          $reports[] = $report;
        }
      }
    }
    \Drupal::entityTypeManager()->getStorage('sms_result')->delete($results);
    \Drupal::entityTypeManager()->getStorage('sms_report')->delete($reports);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    // Save the result and reports in the static cache.
    if ($this->result) {
      $result_entity = SmsMessageResult::convertFromMessageResult($this->result);
      $result_entity
        ->setSmsMessage($this)
        ->save();

      foreach ($this->result->getReports() as $report) {
        $report_entity = SmsDeliveryReport::convertFromDeliveryReport($report);
        $report_entity
          ->setSmsMessage($this)
          ->save();
      }
      // Unset $this->result as we don't need it anymore after save.
      unset($this->result);
    }
  }

}
