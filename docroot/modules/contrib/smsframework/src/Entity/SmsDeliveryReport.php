<?php

declare(strict_types = 1);

namespace Drupal\sms\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\sms\Exception\SmsStorageException;
use Drupal\sms\Message\SmsMessageReportStatus;
use Drupal\sms\Message\SmsDeliveryReportInterface as StdDeliveryReportInterface;

/**
 * Defines the SMS message delivery report entity.
 *
 * The SMS delivery report entity is used to keep track of SMS delivery reports
 * for each recipient.
 *
 * @ContentEntityType(
 *   id = "sms_report",
 *   label = @Translation("SMS Delivery Report"),
 *   label_collection = @Translation("SMS Delivery Reports"),
 *   label_singular = @Translation("SMS delivery report"),
 *   label_plural = @Translation("SMS delivery reports"),
 *   label_count = @PluralTranslation(
 *     singular = "@count SMS delivery report",
 *     plural = "@count SMS delivery reports",
 *   ),
 *   base_table = "sms_report",
 *   revision_table = "sms_report_revision",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "revision" = "vid",
 *   },
 * )
 */
class SmsDeliveryReport extends ContentEntityBase implements SmsDeliveryReportInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getMessageId() {
    return $this->get('message_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessageId($message_id) {
    $this->set('message_id', $message_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipient() {
    return $this->get('recipient')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setRecipient($recipient) {
    $this->set('recipient', $recipient);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusMessage() {
    return $this->get('status_message')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatusMessage($message) {
    $this->set('status_message', $message);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusTime() {
    return $this->get('status_time')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatusTime($time) {
    $this->set('status_time', $time);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeQueued() {
    $queued = $this->getRevisionAtStatus(SmsMessageReportStatus::QUEUED);
    return $queued?->getStatusTime();
  }

  /**
   * {@inheritdoc}
   */
  public function setTimeQueued($time) {
    $this
      ->setStatus(SmsMessageReportStatus::QUEUED)
      ->setStatusTime($time);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeDelivered() {
    $delivered = $this->getRevisionAtStatus(SmsMessageReportStatus::DELIVERED);
    return $delivered?->getStatusTime();
  }

  /**
   * {@inheritdoc}
   */
  public function setTimeDelivered($time) {
    $this
      ->setStatus(SmsMessageReportStatus::DELIVERED)
      ->setStatusTime($time);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSmsMessage() {
    return $this->get('sms_message')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setSmsMessage(SmsMessageInterface $sms_message) {
    $this->set('sms_message', $sms_message);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['message_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Message ID'))
      ->setDescription(t('The message ID assigned to the message.'))
      ->setReadOnly(TRUE)
      ->setDefaultValue('');

    $fields['recipient'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Recipient number'))
      ->setDescription(t('The phone number of the recipient of the message.'))
      ->setReadOnly(TRUE)
      ->setDefaultValue('')
      ->setRequired(TRUE);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Delivery status'))
      ->setDescription(t('A status code from \Drupal\sms\Message\SmsMessageReportStatus.'))
      ->setReadOnly(TRUE)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    $fields['status_message'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Status message'))
      ->setDescription(t('The status message as provided by the gateway API.'))
      ->setReadOnly(TRUE)
      ->setDefaultValue('')
      ->setRequired(FALSE)
      ->setRevisionable(TRUE);

    $fields['status_time'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Status time'))
      ->setDescription(t('The time for the current delivery report status.'))
      ->setReadOnly(TRUE)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the entity was last updated.'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE);

    $fields['sms_message'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'sms')
      ->setLabel(t('SMS Message'))
      ->setDescription(t('The parent SMS message.'))
      ->setReadOnly(TRUE)
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * Gets a revision with the specified delivery report status.
   *
   * @param string $status
   *   Delivery report status from \Drupal\sms\Message\SmsMessageReportStatus.
   *
   * @return \Drupal\sms\Entity\SmsDeliveryReportInterface|null
   *   The delivery report object with that status or null if there is none.
   */
  public function getRevisionAtStatus($status) {
    $storage = $this->entityTypeManager()->getStorage($this->entityTypeId);
    $revision_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($this->getEntityType()->getKey('id'), $this->id())
      ->condition('status', $status)
      ->sort($this->getEntityType()->getKey('revision'), 'DESC')
      ->range(0, 1)
      ->execute();
    if ($revision_ids) {
      return $storage->loadRevision(key($revision_ids));
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    // SMS delivery report cannot be saved without a parent SMS message.
    if (!$this->getSmsMessage()) {
      throw new SmsStorageException('No parent SMS message specified for SMS delivery report');
    }
    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    // Ensure a new revision is saved.
    $this->setNewRevision(TRUE);
    return parent::save();
  }

  /**
   * Converts a plain SMS delivery report into an entity.
   *
   * @param \Drupal\sms\Message\SmsDeliveryReportInterface $sms_report
   *   A plain SMS delivery report.
   *
   * @return \Drupal\sms\Entity\SmsDeliveryReportInterface
   *   An SMS delivery report entity that can be saved.
   */
  public static function convertFromDeliveryReport(StdDeliveryReportInterface $sms_report) {
    if ($sms_report instanceof SmsDeliveryReportInterface) {
      return $sms_report;
    }
    $new = SmsDeliveryReport::create();
    $new
      ->setMessageId($sms_report->getMessageId())
      ->setRecipient($sms_report->getRecipient())
      ->setStatus($sms_report->getStatus())
      ->setStatusMessage($sms_report->getStatusMessage())
      ->setStatusTime($sms_report->getStatusTime());
    return $new;
  }

}
