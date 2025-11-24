<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\private_message\Traits\PrivateMessageTestTrait;
use Drupal\private_message\Entity\PrivateMessageBan;
use Drupal\user\UserInterface;

/**
 * JS tests for autocomplete member widget.
 *
 * @group private_message
 */
class AutocompleteMemberWidgetTest extends WebDriverTestBase {

  use PrivateMessageTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['private_message'];

  /**
   * Tests results provided by autocompletion.
   */
  public function testAutocompleteData(): void {
    // Valid but logged in user.
    $logged = $this->createTestUser($this->requiredPermissions);

    // Valid users (3).
    $this->createTestUser($this->requiredPermissions);
    $a = $this->createTestUser($this->requiredPermissions);
    $b = $this->createTestUser($this->requiredPermissions);
    PrivateMessageBan::create([
      'owner' => $a->id(),
      'target' => $b->id(),
    ]);

    // Invalid users.
    $this->createTestUser([]);
    $userBlocked = $this->createTestUser([]);
    $userBlocked->block()->save();
    $bannedUser = $this->createTestUser([]);
    PrivateMessageBan::create([
      'owner' => $logged->id(),
      'target' => $bannedUser->id(),
    ]);

    $this->drupalLogin($logged);
    $this->drupalGet('/private-message/create');

    $inputElement = $this->assertSession()
      ->waitForElement('css', '#edit-members-target-id');
    $inputElement->setValue('test');

    $autocompleteElement = $this->assertSession()
      ->waitForElementVisible('css', '.ui-autocomplete');

    $this->assertCount(3, $autocompleteElement->findAll('css', 'li a'));
  }

  /**
   * Tests pick from autocompletion widget.
   */
  public function testPickFromAutocompletion(): void {
    $logged = $this->createTestUser($this->requiredPermissions);
    $pickedUser = $this->createTestUser($this->requiredPermissions);

    $this->drupalLogin($logged);

    $this->drupalGet('/private-message/create');

    $inputElement = $this->assertSession()->waitForElement('css', '#edit-members-target-id');
    $inputElement->setValue($pickedUser->getAccountName());

    $autocompleteElement = $this->assertSession()->waitForElementVisible('css', '.ui-autocomplete');
    $results = $autocompleteElement->findAll('css', 'li a');
    $this->assertCount(1, $results);
    $results[0]->click();
    $this->assertEquals($this->getAutocompleteLabel($pickedUser), $inputElement->getValue());

    // Create message.
    $this->getSession()->getPage()->fillField('Subject', 'Test Subject');
    $this->getSession()->getPage()->fillField('Message', 'Test Message');
    $this->getSession()->getPage()->pressButton('Send');

    $this->assertSession()->addressEquals('private-messages/1');
    $this->assertSession()->elementTextContains('css', '.private-message-thread-messages', 'Test Message');
  }

  /**
   * Creates a user whose name begins with 'test'.
   *
   * @param array $permissions
   *   Permissions.
   *
   * @return \Drupal\user\UserInterface
   *   User entity.
   */
  protected function createTestUser(array $permissions): UserInterface {
    return $this->createUser($permissions, NULL, FALSE, [
      'name' => 'test-' . $this->randomMachineName(),
    ]);
  }

}
