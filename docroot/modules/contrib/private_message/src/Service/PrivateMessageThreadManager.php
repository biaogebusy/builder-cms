<?php

declare(strict_types=1);

namespace Drupal\private_message\Service;

use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * The Private Message generator class.
 */
class PrivateMessageThreadManager implements PrivateMessageThreadManagerInterface {

  public function __construct(
    protected readonly PrivateMessageServiceInterface $privateMessageService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function saveThread(PrivateMessageInterface $message, array $members = [], ?PrivateMessageThreadInterface $thread = NULL): void {
    if ($thread === NULL && $members) {
      $thread = $this->privateMessageService->getThreadForMembers($members);
    }

    if ($thread instanceof PrivateMessageThreadInterface) {
      $thread->addMessage($message)->save();
    }
  }

}
