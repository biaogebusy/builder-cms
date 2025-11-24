<?php

declare(strict_types = 1);

namespace Drupal\sms\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event fired when SMS messages are processed.
 *
 * This event can be used for both pre- and post- process events. See
 * {@link \Drupal\sms\Event\SmsEvents} to see where this event is used.
 *
 * @see \Drupal\sms\Event\SmsEvents
 */
class SmsMessageEvent extends Event {

  /**
   * The SMS messages.
   *
   * @var \Drupal\sms\Message\SmsMessageInterface[]
   */
  protected array $messages;

  /**
   * Constructs the object.
   *
   * @param \Drupal\sms\Message\SmsMessageInterface[] $messages
   *   The SMS message.
   */
  public function __construct(array $messages) {
    $this->setMessages($messages);
  }

  /**
   * Get all messages on this event.
   *
   * @return \Drupal\sms\Message\SmsMessageInterface[]
   *   The messages on this event.
   */
  public function getMessages() {
    return $this->messages;
  }

  /**
   * Set the messages on this event.
   *
   * @param \Drupal\sms\Message\SmsMessageInterface[] $messages
   *   The messages to set on this event.
   *
   * @return $this
   *   Returns this event for chaining.
   */
  public function setMessages(array $messages) {
    $this->messages = $messages;
    return $this;
  }

}
