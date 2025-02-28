<?php

namespace Drupal\private_message\Plugin\Menu;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\private_message\Entity\PrivateMessage;

/**
 * Adds private message ID to private message tabs.
 */
class PrivateMessageTab extends LocalTaskDefault {

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match): array {
    $private_message = $route_match->getParameter('private_message');
    if ($private_message instanceof PrivateMessage) {
      $id = $private_message->id();
    }
    else {
      $id = 0;
    }

    return [
      'private_message' => $id,
    ];
  }

}
