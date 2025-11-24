<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\private_message\Entity\PrivateMessageBan;
use Drupal\Tests\private_message\Traits\NotificationBlockTrait;
use Drupal\Tests\private_message\Traits\PrivateMessageTestTrait;

/**
 * JS tests for Private Message Notification block functionalities.
 *
 * @group private_message
 */
class NotificationBlockTest extends WebDriverTestBase {

  use PrivateMessageTestTrait;
  use NotificationBlockTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'private_message'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->attachFullNameField();
    $this->createTestingUsers(3);

    $this->createThreadWithMessages([
      $this->users['a'],
      $this->users['b'],
    ], $this->users['b'], 2);
  }

  /**
   * Tests count functionality.
   *
   * @dataProvider countTypeProvider
   */
  public function testCount(string $countMethod, string $initialCount, string $ajaxCount): void {
    $settings = [
      'ajax_refresh_rate' => 1,
      'count_method' => $countMethod,
    ];
    $this->drupalPlaceBlock('private_message_notification_block', $settings);

    // I should not see a notification for my own message.
    $this->drupalLogin($this->users['b']);
    $this->assertUnreadNotifications('0');
    // When going to a different page, I should still not see a notification for
    // my own message.
    $this->drupalGet('<front>');
    $this->assertUnreadNotifications('0');

    // User should see a notification.
    $this->drupalLogin($this->users['a']);
    $this->assertUnreadNotifications($initialCount);

    // Add more messages.
    $this->createThreadWithMessages([
      $this->users['a'],
      $this->users['c'],
    ], $this->users['c'], 2);
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Notification number should be updated.
    $this->assertUnreadNotifications($ajaxCount);
    $this->assertStringContainsString(
      '(' . $ajaxCount . ')',
      $this->getSession()->getDriver()->getWebDriverSession()->title(),
    );

    // Reset count.
    $this->drupalGet('private-messages');
    $this->assertUnreadNotifications('0');
    $this->assertEquals(
      'Private Messages | Drupal',
      $this->getSession()->getDriver()->getWebDriverSession()->title(),
    );

    // When going to a different page, I should still not see a notification for
    // my own message.
    $this->drupalGet('<front>');
    $this->assertUnreadNotifications('0');
  }

  /**
   * Tests count functionality with after ban.
   *
   * @dataProvider countTypeProvider
   */
  public function testCountForBanned(string $countMethod, string $initialCount): void {
    $settings = [
      'ajax_refresh_rate' => 1,
      'count_method' => $countMethod,
    ];
    $this->drupalPlaceBlock('private_message_notification_block', $settings);

    // User should see a notification.
    $this->drupalLogin($this->users['a']);
    $this->assertUnreadNotifications($initialCount);

    PrivateMessageBan::create([
      'owner' => $this->users['a'],
      'target' => $this->users['b'],
    ])->save();

    // User should not see a notification.
    $this->drupalGet('<front>');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertUnreadNotifications('0');

  }

  /**
   * Data for the testCount().
   */
  public static function countTypeProvider(): array {
    return [
      'Count threads' => ['threads', '1', '2'],
      'Count messages' => ['messages', '2', '4'],
    ];
  }

}
