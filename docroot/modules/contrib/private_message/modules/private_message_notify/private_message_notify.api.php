<?php

/**
 * @file
 * Hook documentation for Private Message Notify module hooks.
 */

use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Allows excluding members from the notifications.
 *
 * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
 *   Private message entity added to the thread.
 * @param \Drupal\private_message\Entity\PrivateMessageThreadInterface $thread
 *   Thread entity a new message is added to.
 * @param array $exclude
 *   Array of UIDs to exclude from notifications.
 *
 * @return void
 */
function hook_private_message_notify_exclude(PrivateMessageInterface $privateMessage, PrivateMessageThreadInterface $thread, array &$exclude) {
  // Allow other modules to exclude notifications recipient,
  // i.e. - check some "mute" flags.
}
