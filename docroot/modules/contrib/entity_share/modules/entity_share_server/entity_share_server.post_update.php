<?php

/**
 * @file
 * Post update functions for Entity Share Server.
 */

declare(strict_types = 1);

use Drupal\Core\Session\AccountInterface;

/**
 * Set a default max size to channels.
 */
function entity_share_server_post_update_set_default_max_size() {
  /** @var \Drupal\entity_share_server\Entity\ChannelInterface[] $channels */
  $channels = \Drupal::entityTypeManager()
    ->getStorage('channel')
    ->loadMultiple();

  foreach ($channels as $channel) {
    $channel->set('channel_maxsize', 50);
    $channel->save();
  }
}

/**
 * Set a default authorized roles and access by permission to channels.
 */
function entity_share_server_post_update_set_default_access_roles_and_permission() {
  /** @var \Drupal\entity_share_server\Entity\ChannelInterface[] $channels */
  $channels = \Drupal::entityTypeManager()
    ->getStorage('channel')
    ->loadMultiple();

  foreach ($channels as $channel) {
    $channel->set('access_by_permission', FALSE);
    $authorized_roles = [];

    // Now handle the case of anonymous user with the role.
    if ($channel->removeAuthorizedUser('anonymous')) {
      $authorized_roles[] = AccountInterface::ANONYMOUS_ROLE;
    }

    $channel->set('authorized_roles', $authorized_roles);
    $channel->save();
  }
}
