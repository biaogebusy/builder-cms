<?php

declare(strict_types=1);

namespace Drupal\private_message\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax command to trigger an update of the private message inbox block.
 */
class PrivateMessageInboxTriggerUpdateCommand implements CommandInterface {

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'privateMessageTriggerInboxUpdate',
    ];
  }

}
