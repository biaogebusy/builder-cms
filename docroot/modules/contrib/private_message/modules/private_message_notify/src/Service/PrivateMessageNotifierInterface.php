<?php

namespace Drupal\private_message_notify\Service;

use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Interface for the Private Message Notify notification service.
 */
interface PrivateMessageNotifierInterface {

  /**
   * Send a private message notification email.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $message
   *   The message.
   * @param \Drupal\private_message\Entity\PrivateMessageThreadInterface $thread
   *   The message thread.
   */
  public function notify(PrivateMessageInterface $message, PrivateMessageThreadInterface $thread);

}
