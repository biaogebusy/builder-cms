<?php

namespace Drupal\private_message\Service;

use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Handles the generation/update of private messages, threads, and metadata.
 *
 * @package Drupal\private_message\Service
 */
interface PrivateMessageThreadManagerInterface {

  /**
   * Saves a private message thread.
   *
   * A new thread will be created if one does not already exist.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $message
   *   The new message object.
   * @param array $recipients
   *   (optional) An array of message recipients. Defaults to an empty array.
   * @param \Drupal\private_message\Entity\PrivateMessageThreadInterface|null $thread
   *   (optional) The private message thread. If NULL, one will be loaded
   *   using the recipients array.
   */
  public function saveThread(PrivateMessageInterface $message, array $recipients = [], PrivateMessageThreadInterface $thread = NULL);

}
