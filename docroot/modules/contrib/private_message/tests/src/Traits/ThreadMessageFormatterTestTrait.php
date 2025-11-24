<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\Traits;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Reusable thread message formatter code.
 */
trait ThreadMessageFormatterTestTrait {

  /**
   * Gets messages.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   Messages.
   */
  protected function getMessages(): array {
    return $this->getSession()
      ->getPage()
      ->findAll('css', '.private-message-thread-messages .private-message-wrapper .private-message');
  }

  /**
   * Gets message ids from markup.
   *
   * @return array
   *   List of message ids.
   */
  protected function getMessageIdsFromMarkup(): array {
    $messageIds = [];
    foreach ($this->getMessages() as $message) {
      $messageIds[] = $message->getAttribute('data-message-id');
    }

    return $messageIds;
  }

  /**
   * Gets message ids for thread.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageThreadInterface $thread
   *   Thread.
   *
   * @return array
   *   List of message ids.
   */
  protected function getMessageIdsForThread(PrivateMessageThreadInterface $thread): array {
    $messageIds = [];
    foreach ($thread->getMessages() as $message) {
      $messageIds[] = $message->id();
    }

    return $messageIds;
  }

  /**
   * Counts messages.
   *
   * @return int
   *   Number of messages.
   */
  protected function countMessages(): int {
    return count($this->getMessages());
  }

  /**
   * Updates widget settings.
   *
   * @param array $settings
   *   Settings to overwrite.
   */
  protected function updateWidgetSettings(array $settings): void {
    $widget = EntityViewDisplay::load('private_message_thread.private_message_thread.default');
    $component = $widget->getComponent('private_messages');
    $component['settings'] = $settings + $component['settings'];

    $widget->setComponent('private_messages', $component)->save();
  }

}
