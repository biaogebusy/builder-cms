<?php

namespace Drupal\Tests\private_message\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the unread count.
 *
 * @group private_message
 */
class PrivateMessageUnreadCountTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['block', 'private_message'];

  /**
   * The first User used for the test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $userA;

  /**
   * The second User used for the test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $userB;

  /**
   * SetUp the test class.
   */
  public function setUp(): void {
    parent::setUp();
    $this->userA = $this->drupalCreateUser([
      'use private messaging system',
      'access user profiles',
    ]);
    $this->userB = $this->drupalCreateUser([
      'use private messaging system',
      'access user profiles',
    ]);

    $this->drupalPlaceBlock('private_message_notification_block');
  }

  /**
   * Tests that the receiving user gets a notification.
   */
  public function testUnreadCounts() {
    $this->drupalLogin($this->userA);

    $this->drupalGet('/private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'members[0][target_id]' => $this->userB->getDisplayName(),
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);
    // I should not see a notification for my own message.
    $this->assertSession()->elementTextContains('css', 'a.private-message-page-link', 0);
    // When going to a different page, I should still not see a notification for
    // my own message.
    $this->drupalGet('<front>');
    $this->assertSession()->elementTextContains('css', 'a.private-message-page-link', 0);

    // User B should see a notification.
    $this->drupalLogin($this->userB);
    $this->assertSession()->elementTextContains('css', 'a.private-message-page-link', 1);

    // We visit the thread directly.
    $this->drupalGet('private-messages/1');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', 'a.private-message-page-link', 0);

    // We are not already looking at the thread.
    $this->submitForm([
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);
    // I should not see a notification for my own message.
    $this->assertSession()->elementTextContains('css', 'a.private-message-page-link', 0);

    // When going to a different page, I should still not see a notification for
    // my own message.
    $this->drupalGet('<front>');
    $this->assertSession()->elementTextContains('css', 'a.private-message-page-link', 0);
  }

}
