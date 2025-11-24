<?php

declare(strict_types = 1);

namespace Drupal\sms\Message;

use Drupal\sms\Entity\SmsGatewayInterface;

/**
 * Basic implementation of an SMS message.
 */
class SmsMessage implements SmsMessageInterface {

  /**
   * The unique identifier for this message.
   *
   * @var string
   */
  protected string $uuid;

  /**
   * The sender's name.
   *
   * @var string|null
   */
  protected ?string $senderName = NULL;

  /**
   * The senders' phone number.
   *
   * @var string|null
   */
  protected ?string $senderPhoneNumber;

  /**
   * The recipients of the message.
   *
   * @var string[]
   */
  protected array $recipients = [];

  /**
   * The content of the message to be sent.
   *
   * @var string
   */
  protected string $message;

  /**
   * The gateway for this message.
   *
   * @var \Drupal\sms\Entity\SmsGatewayInterface|null
   */
  protected ?SmsGatewayInterface $gateway = NULL;

  /**
   * The direction of the message.
   *
   * See \Drupal\sms\Direction constants for potential values.
   *
   * Since direction is not a part of the constructor, it needs to be nullable.
   *
   * @var int|null
   * @see \Drupal\sms\Direction
   */
  protected ?int $direction = NULL;

  /**
   * Other options to be used for the SMS.
   *
   * @var array<mixed>
   */
  protected array $options = [];

  /**
   * The result associated with this SMS message.
   *
   * @var \Drupal\sms\Message\SmsMessageResultInterface|null
   */
  protected ?SmsMessageResultInterface $result = NULL;

  /**
   * The UID of the creator of the SMS message.
   *
   * @var int|null
   */
  protected ?int $uid = NULL;

  /**
   * Whether this message was generated automatically.
   *
   * @var bool
   */
  protected bool $automated = TRUE;

  /**
   * Creates a new instance of an SMS message.
   *
   * @param string|null $sender_phone_number
   *   (optional) The senders' phone number.
   * @param array $recipients
   *   (optional) The list of recipient phone numbers for the message.
   * @param string $message
   *   (optional) The actual SMS message to be sent.
   * @param array $options
   *   (optional) Additional options.
   * @param int|null $uid
   *   (optional) The user who created the SMS message.
   */
  public function __construct(?string $sender_phone_number = NULL, array $recipients = [], string $message = '', array $options = [], ?int $uid = NULL) {
    $this->setSenderNumber($sender_phone_number);
    $this->addRecipients($recipients);
    $this->setMessage($message);
    $this->options = $options;
    $this->setUid($uid);
    $this->uuid = $this->uuidGenerator()->generate();
  }

  /**
   * {@inheritdoc}
   */
  public function getSender() {
    return $this->senderName;
  }

  /**
   * {@inheritdoc}
   */
  public function setSender($sender) {
    $this->senderName = $sender;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSenderNumber() {
    return $this->senderPhoneNumber;
  }

  /**
   * {@inheritdoc}
   */
  public function setSenderNumber($number) {
    $this->senderPhoneNumber = $number;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage() {
    return $this->message;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipients() {
    return $this->recipients;
  }

  /**
   * {@inheritdoc}
   */
  public function addRecipient($recipient) {
    if (!in_array($recipient, $this->recipients, TRUE)) {
      $this->recipients[] = $recipient;
    }
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
    $this->recipients = array_values(array_diff($this->recipients, [$recipient]));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeRecipients(array $recipients) {
    $this->recipients = array_values(array_diff($this->recipients, $recipients));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGateway() {
    return $this->gateway;
  }

  /**
   * {@inheritdoc}
   */
  public function setGateway(SmsGatewayInterface $gateway) {
    $this->gateway = $gateway;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDirection() {
    return $this->direction;
  }

  /**
   * {@inheritdoc}
   */
  public function setDirection($direction) {
    $this->direction = $direction;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public function getOption($name) {
    if (array_key_exists($name, $this->options)) {
      return $this->options[$name];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setOption($name, $value) {
    $this->options[$name] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeOption($name) {
    unset($this->options[$name]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getResult() {
    return $this->result;
  }

  /**
   * {@inheritdoc}
   */
  public function setResult(?SmsMessageResultInterface $result = NULL) {
    $this->result = $result;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    return $this->uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function getUid() {
    return $this->uid;
  }

  /**
   * {@inheritdoc}
   */
  public function setUid($uid) {
    $this->uid = $uid;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setAutomated($automated) {
    $this->automated = $automated;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isAutomated() {
    return $this->automated;
  }

  /**
   * Gets the UUID generator.
   *
   * @return \Drupal\Component\Uuid\UuidInterface
   *   The UUID generator.
   */
  protected function uuidGenerator() {
    return \Drupal::service('uuid');
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

    $base = clone $this;
    $base->removeRecipients($recipients_all);

    $messages = [];
    foreach (array_chunk($recipients_all, $size) as $recipients) {
      $message = clone $base;
      $messages[] = $message->addRecipients($recipients);
    }
    return $messages;
  }

  /**
   * {@inheritdoc}
   */
  public function getReport($recipient) {
    return $this->result?->getReport($recipient);
  }

  /**
   * {@inheritdoc}
   */
  public function getReports() {
    return $this->result ? $this->result->getReports() : [];
  }

}
