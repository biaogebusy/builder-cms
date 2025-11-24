<?php

declare(strict_types=1);

namespace Drupal\private_message\Service;

use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Handles the generation/update of private messages, threads, and metadata.
 */
interface PrivateMessageThreadManagerInterface {

  /**
   * Saves a private message thread.
   *
   * A new thread will be created if one does not already exist.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $message
   *   The new message object.
   * @param array $members
   *   (optional) An array of thread members. Defaults to an empty array.
   * @param \Drupal\private_message\Entity\PrivateMessageThreadInterface|null $thread
   *   (optional) The private message thread. If NULL, one will be loaded
   *   using the members array.
   */
  public function saveThread(PrivateMessageInterface $message, array $members = [], ?PrivateMessageThreadInterface $thread = NULL);

}
