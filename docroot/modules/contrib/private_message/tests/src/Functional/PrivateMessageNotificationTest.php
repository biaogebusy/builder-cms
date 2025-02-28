<?php

namespace Drupal\Tests\private_message\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the Private Message Notification.
 *
 * @group private_message
 */
class PrivateMessageNotificationTest extends BrowserTestBase {
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
  public function testUserReceivesNotification() {
    $this->drupalLogin($this->userA);

    $this->drupalGet('/private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'members[0][target_id]' => $this->userB->getDisplayName(),
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalLogin($this->userB);
    $this->drupalGet('private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', 'a.private-message-page-link', 1);
  }

}
