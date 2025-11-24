<?php

declare(strict_types=1);

namespace Drupal\private_message\Service;

/**
 * The interface for the Private Message Uninstall Service.
 */
interface PrivateMessageUninstallerInterface {

  /**
   * Initiates the batch process for uninstalling messages.
   */
  public function initiateBatch(): void;

}
