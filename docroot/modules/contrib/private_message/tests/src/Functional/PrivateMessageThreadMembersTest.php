<?php

namespace Drupal\Tests\private_message\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the Private Message Recipients.
 *
 * @group private_message
 */
class PrivateMessageThreadMembersTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['private_message'];

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
   * The third User used for the test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $userC;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->userA = $this->drupalCreateUser([
      'use private messaging system',
      'access user profiles',
    ]);
    $this->userB = $this->drupalCreateUser([
      'use private messaging system',
      'access user profiles',
    ]);
    $this->userC = $this->drupalCreateUser([
      'use private messaging system',
      'access user profiles',
    ]);
  }

  /**
   * Tests the thread members.
   */
  public function testThreadMembers() {
    $this->drupalLogin($this->userA);

    $this->drupalGet('/private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->getSession()->getPage()->pressButton('edit-members-add-more');
    $this->assertSession()->fieldExists('members[1][target_id]');
    $this->submitForm([
      'members[0][target_id]' => $this->userB->getDisplayName(),
      'members[1][target_id]' => $this->userC->getDisplayName(),
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.private-message-recipients article:nth-of-type(1) .username', $this->userB->getDisplayName());
    $this->assertSession()->elementTextContains('css', '.private-message-recipients article:nth-of-type(2) .username', $this->userC->getDisplayName());
    $this->drupalLogin($this->userB);
    $this->drupalGet('private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'members[0][target_id]' => $this->userC->getDisplayName(),
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.private-message-recipients .username', $this->userC->getDisplayName());

    $this->drupalLogin($this->userC);
    $this->drupalGet('private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'members[0][target_id]' => $this->userA->getDisplayName(),
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.private-message-recipients .username', $this->userA->getDisplayName());
  }

  /**
   * Tests the autocomplete tags widget.
   */
  public function testThreadMembersWithTagsStyleWidget() {
    $form_display = EntityFormDisplay::load('private_message_thread.private_message_thread.default');
    $members = $form_display->getComponent('members');
    $members['type'] = 'entity_reference_autocomplete_tags';
    $form_display->setComponent('members', $members);
    $form_display->save();

    $this->drupalLogin($this->userA);

    $this->drupalGet('/private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('members[target_id]');
    $this->submitForm([
      'members[target_id]' => $this->userB->getDisplayName() . ' (' . $this->userB->id() . '), ' . $this->userC->getDisplayName() . ' (' . $this->userC->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.private-message-recipients article:nth-of-type(1) .username', $this->userB->getDisplayName());
    $this->assertSession()->elementTextContains('css', '.private-message-recipients article:nth-of-type(2) .username', $this->userC->getDisplayName());
  }

  /**
   * Tests the thread members.
   */
  public function testThreadUniqueness() {
    $this->drupalLogin($this->userA);

    $this->drupalGet('/private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'members[0][target_id]' => $this->userB->getDisplayName(),
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.private-message-recipients article:nth-of-type(1) .username', $this->userB->getDisplayName());

    // Assure that when we create another thread, with an extra user, this
    // group get their own, unique thread.
    $this->drupalGet('/private-message/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->getSession()->getPage()->pressButton('edit-members-add-more');
    $this->assertSession()->fieldExists('members[1][target_id]');
    $this->submitForm([
      'members[0][target_id]' => $this->userB->getDisplayName(),
      'members[1][target_id]' => $this->userC->getDisplayName(),
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.private-message-recipients article:nth-of-type(1) .username', $this->userB->getDisplayName());
    $this->assertSession()->elementTextContains('css', '.private-message-recipients article:nth-of-type(2) .username', $this->userC->getDisplayName());
  }

}
