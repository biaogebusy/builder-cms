<?php

namespace Drupal\commerce;

/**
 * Object containing a single message from the feed.
 */
final class InboxMessage {

  /**
   * Constructs a new InboxMessage object.
   *
   * @param string $id
   *   The ID of the message.
   * @param string $subject
   *   The subject of the message.
   * @param string $message
   *   The text of the message.
   * @param string $cta_text
   *   The CTA text.
   * @param string $cta_link
   *   The CTA link.
   * @param int $send_date
   *   The send date timestamp.
   * @param string $state
   *   The state.
   */
  public function __construct(
    public string $id,
    public string $subject,
    public string $message,
    public string $cta_text,
    public string $cta_link,
    public int $send_date,
    public string $state,
  ) {
  }

  /**
   * Creates a new InboxMessage object from the given array.
   *
   * @param array $message
   *   The message array.
   *
   * @return static
   *   The instantiated InboxMessage.
   */
  public static function fromArray(array $message): self {
    if (!isset($message['id'], $message['subject'], $message['message'], $message['cta_text'], $message['cta_link'], $message['send_date'], $message['state'])) {
      throw new \InvalidArgumentException('InboxMessage::fromArray() called with a malformed array.');
    }
    return new static(
      $message['id'],
      $message['subject'],
      $message['message'],
      $message['cta_text'],
      $message['cta_link'],
      $message['send_date'],
      $message['state']
    );
  }

  /**
   * Gets whether the message is in an unread state.
   *
   * @return bool
   *   Whether the message is unread.
   */
  public function isUnread(): bool {
    return $this->state === 'unread';
  }

}
