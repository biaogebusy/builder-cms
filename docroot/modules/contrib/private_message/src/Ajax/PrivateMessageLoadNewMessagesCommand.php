<?php

declare(strict_types=1);

namespace Drupal\private_message\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax Command to trigger a load of new private messages into a thread.
 */
class PrivateMessageLoadNewMessagesCommand implements CommandInterface {

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'loadNewPrivateMessages',
    ];
  }

}
