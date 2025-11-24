<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

/**
 * Tests simultaneous edit on test entity.
 *
 * @group content_lock
 */
class ContentLockEntityTest extends ContentLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests simultaneous edit on test entity.
   */
  public function testContentLockEntity() {

    // We protect the bundle created.
    $this->drupalLogin($this->admin);
    $edit = [
      'entity_test_mul_changed[bundles][*]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    // We lock entity.
    $this->drupalLogin($this->user1);
    // Edit a entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit entity.
    $this->drupalLogin($this->user2);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains("This content is being edited by the user {$this->user1->getDisplayName()} and is therefore locked to prevent other users changes.");
    $assert_session->linkExists('Break lock');
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));
    $input = $this->assertSession()->elementExists('css', 'input#edit-field-test-text-0-value');
    $this->assertTrue($input->hasAttribute('disabled'));

    // We save entity 1 and unlock it.
    $this->drupalLogin($this->user1);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $assert_session->pageTextNotContains('against simultaneous editing.');

    // We lock entity with user2.
    $this->drupalLogin($this->user2);
    // Edit a entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit entity.
    $this->drupalLogin($this->user1);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains("This content is being edited by the user {$this->user2->getDisplayName()} and is therefore locked to prevent other users changes.");
    $assert_session->linkNotExists('Break lock');
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));

    // We unlock entity with user2.
    $this->drupalLogin($this->user2);
    // Edit a entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $assert_session->pageTextContains('updated.');
    $assert_session->pageTextNotContains('against simultaneous editing.');
  }

}
