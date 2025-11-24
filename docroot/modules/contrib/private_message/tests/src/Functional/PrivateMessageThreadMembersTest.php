<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\private_message\Traits\PrivateMessageTestTrait;

/**
 * Tests for the Private Message Recipients.
 *
 * @group private_message
 */
class PrivateMessageThreadMembersTest extends BrowserTestBase {

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
  protected function setUp(): void {
    parent::setUp();
    $this->attachFullNameField();
    $this->createTestingUsers(3);
  }

  /**
   * Tests the thread members.
   */
  public function testThreadMembers(): void {
    $this->drupalLogin($this->users['a']);

    $this->drupalGet('/private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('members[target_id]');
    $this->submitForm([
      'members[target_id]' => $this->getAutocompleteLabel($this->users['b']) . ', ' . $this->getAutocompleteLabel($this->users['c']),
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.private-message-recipients article:nth-of-type(1) .username', $this->users['b']->getDisplayName());
    $this->assertSession()->elementTextContains('css', '.private-message-recipients article:nth-of-type(2) .username', $this->users['c']->getDisplayName());
    $this->drupalLogin($this->users['b']);
    $this->drupalGet('private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'members[target_id]' => $this->getAutocompleteLabel($this->users['c']),
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.private-message-recipients .username', $this->users['c']->getDisplayName());

    $this->drupalLogin($this->users['c']);
    $this->drupalGet('private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'members[target_id]' => $this->getAutocompleteLabel($this->users['a']),
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.private-message-recipients .username', $this->users['a']->getDisplayName());
  }

  /**
   * Tests validation errors.
   */
  public function testValidationErrors(): void {
    $this->drupalLogin($this->users['a']);

    $this->drupalGet('/private-message/create');
    $this->submitForm([], 'Send');
    $this->assertSession()->pageTextContains('To field is required.');
    $this->assertSession()->pageTextContains('Message field is required.');

    $randomUser = $this->getRandomGenerator()->sentences(5);
    $this->submitForm([
      'members[target_id]' => $randomUser,
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()
      ->pageTextContains('There are no users matching "' . $randomUser . '"');

    $this->submitForm([
      'members[target_id]' => $this->getAutocompleteLabel($this->users['a']),
    ], 'Send');
    $this->assertSession()
      ->pageTextContains('You can not send a message to yourself only.');

    $blockedUser = $this->createUser([], NULL, FALSE, ['full_name' => $this->randomMachineName()]);
    $blockedUser->block()->save();
    $this->submitForm([
      'members[target_id]' => $this->getAutocompleteLabel($blockedUser),
    ], 'Send');
    $this->assertSession()
      ->pageTextContains('You can not send a message because there are inactive users selected for this thread.');

    $constraintUser = $this->createUser([], NULL, FALSE, ['full_name' => $this->randomMachineName()]);
    $this->submitForm([
      'members[target_id]' => $this->getAutocompleteLabel($constraintUser),
    ], 'Send');
    $this->assertSession()
      ->pageTextContains('User ' . $constraintUser->getDisplayName() . ' not found');
  }

  /**
   * Tests the thread members.
   */
  public function testThreadUniqueness(): void {
    $this->drupalLogin($this->users['a']);

    $this->drupalGet('/private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'members[target_id]' => $this->getAutocompleteLabel($this->users['b']),
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.private-message-recipients article:nth-of-type(1) .username', $this->users['b']->getDisplayName());

    // Assure that when we create another thread, with an extra user, this
    // group get their own, unique thread.
    $this->drupalGet('/private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'members[target_id]' => $this->getAutocompleteLabel($this->users['b']) . ', ' . $this->getAutocompleteLabel($this->users['c']),
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.private-message-recipients article:nth-of-type(1) .username', $this->users['b']->getDisplayName());
    $this->assertSession()->elementTextContains('css', '.private-message-recipients article:nth-of-type(2) .username', $this->users['c']->getDisplayName());
  }

}
