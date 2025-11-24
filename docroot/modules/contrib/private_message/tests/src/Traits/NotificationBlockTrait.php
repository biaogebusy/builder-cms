<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\Traits;

/**
 * Reusable notification block code.
 */
trait NotificationBlockTrait {

  /**
   * Asserts unread notifications.
   *
   * @param string $expectedUnread
   *   Expected unread value.
   */
  protected function assertUnreadNotifications(string $expectedUnread): void {
    $unreadElement = $this->assertSession()
      ->elementExists('css', 'a.private-message-page-link');

    $this->assertEquals($expectedUnread, $unreadElement->getText(), 'Incorrect unread value.');
  }

}
