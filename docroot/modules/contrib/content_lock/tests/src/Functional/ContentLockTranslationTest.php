<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Content lock translation tests.
 *
 * @group content_lock
 */
#[RunTestsInSeparateProcesses]
class ContentLockTranslationTest extends ContentLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'conflict',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test translation integration.
   */
  public function testTranslatedContent(): void {
    $translation = $this->entity->addTranslation('de', ['name' => 'entity1 german']);
    $this->entity->save();

    // We protect the bundle created.
    $this->drupalLogin($this->admin);
    $edit = [
      'entity_test_mul_changed[bundles][*]' => 1,
      'entity_test_mul_changed[settings][translation_lock]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    /** @var \Drupal\content_lock\ContentLock\ContentLock $lockService */
    $lockService = \Drupal::service('content_lock');

    $assert_session = $this->assertSession();

    // We lock entity.
    $this->drupalLogin($this->user1);
    // Edit an entity without saving.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains('This content translation is now locked against simultaneous editing. This content translation will remain locked if you navigate away from this page without saving or unlocking it.');
    // English form locked, german not.
    $this->assertNotFalse($lockService->fetchLock($this->entity));
    $this->assertFalse($lockService->fetchLock($translation));

    $this->drupalLogin($this->user2);
    // Enter english form.
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $assert_session->pageTextContains('This content translation is being edited by the user');
    $this->getSession()->getPage()->clickLink('Break the lock.');
    $this->getSession()->getPage()->pressButton('Confirm break lock');
    $this->assertSame($this->entity->toUrl('edit-form', ['absolute' => TRUE])->toString(), $this->getUrl());
    $assert_session->pageTextContains('This content translation is now locked against simultaneous editing. This content translation will remain locked if you navigate away from this page without saving or unlocking it.');

    // Enter translation form.
    $this->drupalGet($translation->toUrl('edit-form'));
    $assert_session->pageTextContains('This content translation is now locked against simultaneous editing. This content translation will remain locked if you navigate away from this page without saving or unlocking it.');
    $this->assertNotFalse($lockService->fetchLock($translation));
    $this->drupalGet($translation->toUrl('edit-form'));
    $this->submitForm([], 'Save');

    $this->drupalLogin($this->user1);
    $this->drupalGet($translation->toUrl('edit-form'));
    $assert_session->pageTextContains('This content translation is now locked against simultaneous editing. This content translation will remain locked if you navigate away from this page without saving or unlocking it.');

    $this->drupalLogin($this->user2);
    $this->drupalGet($translation->toUrl('edit-form'));
    $assert_session->pageTextContains('This content translation is being edited by the user');
    $this->getSession()->getPage()->clickLink('Break the lock.');
    $this->getSession()->getPage()->pressButton('Confirm break lock');
    $this->assertSame($translation->toUrl('edit-form', ['absolute' => TRUE])->toString(), $this->getUrl());
    $assert_session->pageTextContains('This content translation is now locked against simultaneous editing. This content translation will remain locked if you navigate away from this page without saving or unlocking it.');
  }

}
