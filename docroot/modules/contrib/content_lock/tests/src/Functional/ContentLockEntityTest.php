<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\entity_test\Entity\EntityTestMulChanged;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests simultaneous edit on test entity.
 *
 * @group content_lock
 */
#[RunTestsInSeparateProcesses]
class ContentLockEntityTest extends ContentLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests simultaneous edit on test entity.
   */
  public function testContentLockEntity(): void {

    // We protect the bundle created.
    $this->drupalLogin($this->admin);
    $edit = [
      'entity_test_mul_changed[bundles][*]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->assertSession()->pageTextContains((string) \Drupal::entityTypeManager()->getDefinition('entity_test_mul_changed')->getLabel());
    $this->assertSession()->pageTextNotContains((string) \Drupal::entityTypeManager()->getDefinition('entity_test_string_id')->getLabel());
    $this->submitForm($edit, 'Save configuration');

    // We lock entity.
    $this->drupalLogin($this->user1);
    // Edit an entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit entity.
    $this->drupalLogin($this->user2);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains("This content is being edited by the user {$this->user1->getDisplayName()} and is therefore locked to prevent changes by other users");
    $assert_session->linkExists('Break the lock.');
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
    // Edit an entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit entity.
    $this->drupalLogin($this->user1);
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains("This content is being edited by the user {$this->user2->getDisplayName()} and is therefore locked to prevent changes by other users");
    $assert_session->linkNotExists('Break the lock.');
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));

    // We unlock entity with user2.
    $this->drupalLogin($this->user2);
    // Edit an entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $assert_session->pageTextContains('updated.');
    $assert_session->pageTextNotContains('against simultaneous editing.');
  }

  /**
   * Tests deleting entities with content locks.
   *
   * @covers content_lock_entity_access
   */
  public function testContentLockEntityDeleteAccess(): void {
    // Create two additional test entities.
    $entity1 = EntityTestMulChanged::create([
      'name' => 'Entity for user without break permission',
    ]);
    $entity1->save();

    $entity2 = EntityTestMulChanged::create([
      'name' => 'Entity for user with break permission',
    ]);
    $entity2->save();

    // We protect the bundle.
    $this->drupalLogin($this->admin);
    $edit = [
      'entity_test_mul_changed[bundles][*]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    // Lock both entities.
    $this->drupalGet($entity1->toUrl('edit-form'));
    $this->drupalGet($entity2->toUrl('edit-form'));

    // Test user1 (without break lock permission) cannot delete the locked
    // entity.
    $this->drupalLogin($this->user1);
    $url = $entity1->toUrl('delete-form')->toString();
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

    // Test user2 (with break lock permission) can delete the locked entity.
    $this->drupalLogin($this->user2);
    $url = $entity2->toUrl('delete-form')->toString();
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    // In order to delete the entity this way we will need to break the lock.
    // This is test above.
  }

}
