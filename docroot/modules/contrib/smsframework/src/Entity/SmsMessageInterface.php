<?php

declare(strict_types = 1);

namespace Drupal\sms\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\sms\Message\SmsMessageInterface as PlainSmsMessageInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for SMS message entity.
 */
interface SmsMessageInterface extends ContentEntityInterface, PlainSmsMessageInterface {

  /**
   * Gets the entity who sent the SMS message.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity who sent the SMS message, or NULL if it is missing.
   */
  public function getSenderEntity();

  /**
   * Set the entity who sent the SMS message.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity who sent the SMS message.
   *
   * @return $this
   *   Return SMS message for chaining.
   */
  public function setSenderEntity(EntityInterface $entity);

  /**
   * Gets the entity who will receive the SMS message.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity who will receive the SMS message, or NULL if it is missing.
   */
  public function getRecipientEntity();

  /**
   * Set the entity who will receive the SMS message.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity who will receive the SMS message.
   *
   * @return $this
   *   Return SMS message for chaining.
   */
  public function setRecipientEntity(EntityInterface $entity);

  /**
   * Get whether the SMS message is in the queue to be processed.
   *
   * @return bool
   *   Whether the SMS message is in the queue to be processed.
   */
  public function isQueued();

  /**
   * Get whether the SMS message is in the queue to be processed.
   *
   * @param bool $is_queued
   *   Whether the SMS message is in the queue to be processed.
   *
   * @return $this
   *   Return SMS message for chaining.
   */
  public function setQueued($is_queued);

  /**
   * Get the creation timestamp of the SMS message.
   *
   * @return int
   *   Creation timestamp of the SMS message.
   */
  public function getCreatedTime();

  /**
   * Get the time to send the SMS message.
   *
   * @return int
   *   The timestamp after which the SMS message should be sent.
   */
  public function getSendTime();

  /**
   * Set the time to send the SMS message.
   *
   * @param int $send_time
   *   The timestamp after which the SMS message should be sent.
   *
   * @return $this
   *   Return SMS message for chaining.
   */
  public function setSendTime($send_time);

  /**
   * The time the SMS message was processed.
   *
   * This value does not indicate whether the message was sent, only that the
   * gateway accepted the request.
   *
   * @return int|null
   *   The timestamp when SMS message was processed, or NULL if not processed.
   */
  public function getProcessedTime();

  /**
   * Set the time the SMS message was processed.
   *
   * @param int $processed
   *   The timestamp when SMS message was processed.
   *
   * @return $this
   *   Return SMS message for chaining.
   */
  public function setProcessedTime($processed);

}
