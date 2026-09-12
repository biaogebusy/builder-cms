<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\FunctionalJavascript;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests locking.
 *
 * @group content_lock
 */
#[RunTestsInSeparateProcesses]
class ContentLockEntityTest extends ContentLockJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests locking.
   */
  public function testLocking(): void {
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->admin);
    $this->drupalGet('admin/config/content/content_lock');
    $this->click('#edit-entity-types-entity-test-mul-changed');
    $page->pressButton('Save configuration');

    // We lock entity.
    $this->drupalLogin($this->user1);
    // Edit an entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session = $this->assertSession();
    $this->assertTrue($assert_session->waitForText('This content is now locked against simultaneous editing.'));

    // Other user can not edit entity.
    $this->drupalLogin($this->user2);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->assertTrue($assert_session->waitForText("This content is being edited by the user {$this->user1->getDisplayName()} and is therefore locked to prevent changes by other users."));
    $this->htmlOutput();
    $assert_session->linkExists('Break the lock.');
    $assert_session->elementExists('css', 'input[disabled][data-drupal-selector="edit-submit"]');
    // Fields are disabled.
    $input = $this->assertSession()->elementExists('css', 'input#edit-field-test-text-0-value');
    $this->assertTrue($input->hasAttribute('disabled'));

    // We save entity 1 and unlock it.
    $this->drupalLogin($this->user1);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->assertTrue($assert_session->waitForText('This content is now locked by you against simultaneous editing.'));
    $page->pressButton('Save');

    // We lock entity with user2.
    $this->drupalLogin($this->user2);
    // Edit an entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->assertTrue($assert_session->waitForText('This content is now locked against simultaneous editing.'));

    // Other user can not edit entity.
    $this->drupalLogin($this->user1);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->assertTrue($assert_session->waitForText("This content is being edited by the user {$this->user2->getDisplayName()} and is therefore locked to prevent changes by other users."));
    $assert_session->linkNotExists('Break the lock.');
    // Ensure the input is disabled.
    $assert_session->elementExists('css', 'input[disabled][data-drupal-selector="edit-submit"]');

    // We unlock entity with user2.
    $this->drupalLogin($this->user2);
    // Edit an entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->assertTrue($assert_session->waitForText('This content is now locked by you against simultaneous editing.'));
    $page->pressButton('Save');
    $this->assertTrue($assert_session->waitForText('updated.'));
  }

}
