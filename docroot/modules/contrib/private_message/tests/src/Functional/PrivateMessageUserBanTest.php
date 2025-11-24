<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\private_message\Traits\PrivateMessageTestTrait;

/**
 * Tests for the Private Message bans.
 *
 * @group private_message
 */
class PrivateMessageUserBanTest extends BrowserTestBase {

  use PrivateMessageTestTrait;

  /**
   * {@inheritdoc}
   */

  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['private_message_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->attachFullNameField();
    $this->createTestingUsers();
  }

  /**
   * Tests that it's possible to block and unblock the user.
   */
  public function testBlockingUser(): void {
    $this->drupalLogin($this->users['a']);

    // Block the user.
    $this->drupalGet($this->users['b']->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkNotExists('Unblock');
    $this->clickLink('Block');
    $this->assertSession()->pageTextContains("Are you sure you want to block user {$this->users['b']->getDisplayName()}?");
    $this->submitForm([], 'Confirm');
    $this->assertSession()->pageTextContains("The user {$this->users['b']->getDisplayName()} has been banned.");

    $this->assertEquals(1, $this->retrieveBansCount());

    // Then unblock.
    $this->drupalGet($this->users['b']->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkNotExists('Block');
    $this->clickLink('Unblock');
    $this->assertSession()->pageTextContains("Are you sure you want to unblock user {$this->users['b']->getDisplayName()}?");
    $this->submitForm([], 'Confirm');
    $this->assertSession()->pageTextContains("The user {$this->users['b']->getDisplayName()} has been unbanned.");

    $this->assertEquals(0, $this->retrieveBansCount());

    // Check that it's possible to block user again.
    $this->drupalGet($this->users['b']->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkNotExists('Unblock');
    $this->assertSession()->linkExists('Block');
  }

  /**
   * Tests access to the ban listing.
   */
  public function testBanPageRouteAccess() {
    $this->drupalLogin($this->users['a']);
    $this->drupalGet('/private-message/ban');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Ban/Unban users');

    $this->drupalLogout();
    $this->drupalGet('/private-message/ban');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests for BanUserForm.
   */
  public function testBanUserFormSubmission() {
    $this->drupalLogin($this->users['a']);
    $this->drupalGet('/private-message/ban');

    $edit = [
      'banned_user' => $this->getAutocompleteLabel($this->users['a']),
    ];
    $this->submitForm($edit, 'Block');
    $this->assertSession()->statusMessageContains("You can't block yourself.", 'error');

    $edit = [
      'banned_user' => $this->getAutocompleteLabel($this->users['b']),
    ];
    $this->submitForm($edit, 'Block');
    $this->assertSession()->statusMessageContains("The user " . $this->users['b']->getDisplayName() . " has been banned.", 'status');

    $edit = [
      'banned_user' => $this->getAutocompleteLabel($this->users['b']),
    ];
    $this->submitForm($edit, 'Block');
    $this->assertSession()->statusMessageContains('The user is already blocked', 'error');
  }

  /**
   * Returns a count of bans.
   */
  protected function retrieveBansCount(): int {
    return $this
      ->container
      ->get('entity_type.manager')
      ->getStorage('private_message_ban')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

}
