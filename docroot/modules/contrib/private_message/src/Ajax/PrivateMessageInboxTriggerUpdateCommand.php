<?php

namespace Drupal\private_message\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax command to trigger an update of the private message inbox block.
 */
class PrivateMessageInboxTriggerUpdateCommand implements CommandInterface {

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'privateMessageTriggerInboxUpdate',
    ];
  }

}
