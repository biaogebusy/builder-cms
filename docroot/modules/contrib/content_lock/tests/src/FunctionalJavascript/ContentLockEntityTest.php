<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\FunctionalJavascript;

/**
 * Tests JS locking.
 *
 * @group content_lock
 */
class ContentLockEntityTest extends ContentLockJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests JS locking.
   */
  public function testJsLocking() {
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->admin);
    $this->drupalGet('admin/config/content/content_lock');
    $this->click('#edit-entity-types-entity-test-mul-changed');
    $this->click('#edit-entity-test-mul-changed-settings-js-lock');
    $page->pressButton('Save configuration');

    // We lock entity.
    $this->drupalLogin($this->user1);
    // Edit a entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit entity.
    $this->drupalLogin($this->user2);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains("This content is being edited by the user {$this->user1->getDisplayName()} and is therefore locked to prevent other users changes.");
    $this->htmlOutput();
    $assert_session->linkExists('Break lock');
    $assert_session->elementExists('css', 'input[disabled][data-drupal-selector="edit-submit"]');
    // Fields are disabled.
    $input = $this->assertSession()->elementExists('css', 'input#edit-field-test-text-0-value');
    $this->assertTrue($input->hasAttribute('disabled'));

    // We save entity 1 and unlock it.
    $this->drupalLogin($this->user1);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $page->pressButton('Save');

    // We lock entity with user2.
    $this->drupalLogin($this->user2);
    // Edit a entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit entity.
    $this->drupalLogin($this->user1);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains("This content is being edited by the user {$this->user2->getDisplayName()} and is therefore locked to prevent other users changes.");
    $assert_session->linkNotExists('Break lock');
    // Ensure the input is disabled.
    $assert_session->elementExists('css', 'input[disabled][data-drupal-selector="edit-submit"]');

    // We unlock entity with user2.
    $this->drupalLogin($this->user2);
    // Edit a entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $page->pressButton('Save');
    $assert_session->waitForElement('css', 'messages messages--status');
    $assert_session->pageTextContains('updated.');
  }

}
