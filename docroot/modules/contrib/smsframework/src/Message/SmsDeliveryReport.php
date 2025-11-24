<?php

declare(strict_types = 1);

namespace Drupal\sms\Message;

/**
 * A value object that holds the SMS delivery report.
 */
class SmsDeliveryReport implements SmsDeliveryReportInterface {

  /**
   * The unique identifier for the message assigned by the gateway.
   *
   * @var string
   */
  protected string $messageId = '';

  /**
   * The recipient of the message.
   *
   * @var string
   */
  protected string $recipient = '';

  /**
   * Status code for the message.
   *
   * A status code from \Drupal\sms\Message\SmsMessageStatus, or NULL if
   * unknown.
   *
   * @var string|null
   */
  protected ?string $status = NULL;

  /**
   * The status message as provided by the gateway API.
   *
   * @var string
   */
  protected string $statusMessage = '';

  /**
   * The timestamp when the delivery report status was updated.
   *
   * @var int
   */
  protected ?int $statusTime = NULL;

  /**
   * The timestamp when the message was queued, or NULL if unknown.
   *
   * @var int|null
   */
  protected ?int $timeQueued = NULL;

  /**
   * The timestamp when the message was delivered, or NULL if unknown.
   *
   * @var int|null
   */
  protected ?int $timeDelivered = NULL;

  /**
   * {@inheritdoc}
   */
  public function getMessageId() {
    return $this->messageId;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessageId($message_id) {
    $this->messageId = $message_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipient() {
    return $this->recipient;
  }

  /**
   * {@inheritdoc}
   */
  public function setRecipient($recipient) {
    $this->recipient = $recipient;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->status = $status;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusMessage() {
    return $this->statusMessage;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatusMessage($message) {
    $this->statusMessage = $message;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeQueued() {
    return $this->timeQueued;
  }

  /**
   * {@inheritdoc}
   */
  public function setTimeQueued($time) {
    $this->timeQueued = $time;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeDelivered() {
    return $this->timeDelivered;
  }

  /**
   * {@inheritdoc}
   */
  public function setTimeDelivered($time) {
    $this->timeDelivered = $time;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusTime() {
    return $this->statusTime;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatusTime($time) {
    $this->statusTime = $time;
    return $this;
  }

}
