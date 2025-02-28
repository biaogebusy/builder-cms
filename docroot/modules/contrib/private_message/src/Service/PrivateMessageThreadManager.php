<?php

namespace Drupal\private_message\Service;

use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * The Private Message generator class.
 *
 * @package Drupal\private_message\Service
 */
class PrivateMessageThreadManager implements PrivateMessageThreadManagerInterface {

  /**
   * The private message service.
   *
   * @var \Drupal\private_message\Service\PrivateMessageServiceInterface
   */
  private PrivateMessageServiceInterface $privateMessageService;

  /**
   * The private message.
   *
   * @var \Drupal\private_message\Entity\PrivateMessageInterface|null
   */
  private ?PrivateMessageInterface $message;

  /**
   * The message recipients.
   *
   * @var \Drupal\Core\Session\AccountInterface[]
   */
  private array $recipients = [];

  /**
   * The private message thread.
   *
   * @var \Drupal\private_message\Entity\PrivateMessageThreadInterface|null
   */
  private ?PrivateMessageThreadInterface $thread;

  /**
   * PrivateMessageThreadManager constructor.
   *
   * @param \Drupal\private_message\Service\PrivateMessageServiceInterface $privateMessageService
   *   The private message service.
   */
  public function __construct(
    PrivateMessageServiceInterface $privateMessageService
  ) {
    $this->privateMessageService = $privateMessageService;
  }

  /**
   * {@inheritdoc}
   */
  public function saveThread(PrivateMessageInterface $message, array $recipients = [], PrivateMessageThreadInterface $thread = NULL): void {
    $this->message = $message;
    $this->thread = $thread;
    $this->recipients = $recipients;

    $this->getThread()->addMessage();
  }

  /**
   * If no thread is defined, load one from the thread members.
   *
   * @return $this
   */
  private function getThread(): PrivateMessageThreadManagerInterface {
    if (is_null($this->thread)) {
      $this->thread = $this->privateMessageService->getThreadForMembers($this->recipients);
    }

    return $this;
  }

  /**
   * Add the new message to the thread.
   *
   * @return $this
   */
  private function addMessage(): PrivateMessageThreadManagerInterface {
    if ($this->thread) {
      $this->thread->addMessage($this->message);
      $this->thread->save();
    }

    return $this;
  }

}
