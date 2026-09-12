<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\content_lock_test\ReleaseRecorder;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_lock\Tools\LogoutTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Node tests.
 *
 * @group content_lock
 */
#[RunTestsInSeparateProcesses]
class ContentLockNodeTest extends BrowserTestBase {
  use CountLocksTestTrait;
  use LogoutTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'node',
    'ckeditor5',
    'content_lock',
    'content_lock_test',
  ];

  /**
   * Test simultaneous edit on content type article.
   */
  public function testEdit(): void {
    $assert_session = $this->assertSession();

    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'CKEditor 5 with link',
    ])->save();
    Editor::create([
      'format' => 'test_format',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => ['link'],
        ],
      ],
    ])->save();

    $this->drupalCreateContentType(['type' => 'article']);
    $article = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Article 1',
      'body' => [
        'value' => '<p>This is a test!</p>',
        'format' => 'test_format',
      ],

    ]);

    $admin = $this->drupalCreateUser([
      'use text format test_format',
      'edit any article content',
      'delete any article content',
      'administer nodes',
      'administer content types',
      'administer content lock',
    ]);

    $user1 = $this->drupalCreateUser([
      'use text format test_format',
      'create article content',
      'edit any article content',
      'delete any article content',
      'access content',
    ]);
    $user2 = $this->drupalCreateUser([
      'use text format test_format',
      'create article content',
      'edit any article content',
      'delete any article content',
      'access content',
      'break content lock',
    ]);

    // Protect the bundle created.
    $this->drupalLogin($admin);
    $edit = [
      'node[bundles][article]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    // Test message does not appear after saving.
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');
    $this->submitForm([], 'Save');
    $assert_session->pageTextNotContains('simultaneous editing');
    $this->assertFalse(\Drupal::service('content_lock')->fetchLock($article), 'Content is not locked');

    // Test message still appears after previewing.
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');
    $this->submitForm([], 'Preview');
    $this->assertNotFalse(\Drupal::service('content_lock')->fetchLock($article), 'Content is locked');
    $this->getSession()->getPage()->clickLink('Back to content editing');
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->getSession()->getPage()->clickLink('Unlock');
    $this->getSession()->getPage()->pressButton('Confirm break lock');

    // Lock article1.
    $this->drupalLogin($user1);
    // Edit a node without saving.
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit article1.
    $this->drupalLogin($user2);
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains("This content is being edited by the user {$user1->getDisplayName()} and is therefore locked to prevent changes by other users");
    $assert_session->linkExists('Break the lock.');
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));
    $textarea = $assert_session->elementExists('css', 'textarea#edit-body-0-value');
    $this->assertTrue($textarea->hasAttribute('disabled'));

    // Save article 1 and unlock it.
    $this->drupalLogin($user1);
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->drupalGet('/node/' . $article->id() . '/edit');
    $this->submitForm([], 'Save');

    // Lock article1 with user2.
    $this->drupalLogin($user2);
    // Edit a node without saving.
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit article1.
    $this->drupalLogin($user1);
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains("This content is being edited by the user {$user2->getDisplayName()} and is therefore locked to prevent changes by other users");
    $assert_session->linkNotExists('Break the lock.');
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));

    // We unlock article1 with user2.
    $this->drupalLogin($user2);
    // Edit a node without saving.
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->drupalGet('/node/' . $article->id() . '/edit');
    $this->submitForm([], 'Save');
    $assert_session->pageTextContains('updated.');

  }

  /**
   * Tests deleting nodes with content locks.
   *
   * @covers content_lock_entity_access
   */
  public function testDeleteAccess(): void {
    $this->drupalCreateContentType(['type' => 'article']);

    // Create two test nodes.
    $article1 = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Article for user without break permission',
      'body' => [
        'value' => '<p>Test article 1</p>',
        'format' => 'test_format',
      ],
    ]);

    $article2 = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Article for user with break permission',
      'body' => [
        'value' => '<p>Test article 2</p>',
        'format' => 'test_format',
      ],
    ]);

    $admin = $this->drupalCreateUser([
      'edit any article content',
      'delete any article content',
      'administer nodes',
      'administer content types',
      'administer content lock',
      'access content overview',
    ]);

    // User without break lock permission.
    $user1 = $this->drupalCreateUser([
      'create article content',
      'edit any article content',
      'delete any article content',
      'access content',
      'access content overview',
    ]);

    // User with break lock permission.
    $user2 = $this->drupalCreateUser([
      'create article content',
      'edit any article content',
      'delete any article content',
      'access content',
      'break content lock',
      'access content overview',
    ]);

    // Enable content lock for article nodes.
    $this->drupalLogin($admin);
    $edit = [
      'node[bundles][article]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    // Lock both articles.
    $this->drupalGet("node/{$article1->id()}/edit");
    $this->drupalGet("node/{$article2->id()}/edit");

    // Test user1 (without break lock permission) cannot delete the locked
    // articles.
    $this->drupalLogin($user1);
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/content');
    $assert_session->fieldExists('node_bulk_form[0]')->check();
    $assert_session->fieldExists('node_bulk_form[1]')->check();
    $assert_session->fieldExists('edit-action')->selectOption('node_delete_action');
    $assert_session->buttonExists('Apply to selected items')->press();
    $assert_session->statusCodeEquals(200);
    $assert_session->addressEquals('admin/content');
    $assert_session->pageTextContains('No access to execute Delete content on the Content Article for user with break permission.');
    $assert_session->pageTextContains('No access to execute Delete content on the Content Article for user without break permission.');

    $this->drupalLogin($admin);
    $this->drupalGet("node/{$article1->id()}/edit");
    $this->clickLink('Unlock');
    $this->assertEmpty(\Drupal::service(ReleaseRecorder::class)->getReleasedEntities());
    $this->submitForm([], 'Confirm break lock');
    $released_entities = \Drupal::service(ReleaseRecorder::class)->getReleasedEntities();
    $this->assertCount(1, $released_entities);
    $this->assertSame([
      'entity_id' => $article1->id(),
      'langcode' => 'und',
      'form_op' => '*',
      'entity_type' => $article1->getEntityTypeId(),
    ], $released_entities[0]);

    // Test user1 (without break lock permission) can delete the locked articles
    // when it is their lock.
    $this->drupalLogin($user1);
    $this->drupalGet("node/{$article1->id()}/edit");
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/content');
    $assert_session->fieldExists('node_bulk_form[0]')->check();
    $assert_session->fieldExists('node_bulk_form[1]')->check();
    $assert_session->fieldExists('edit-action')->selectOption('node_delete_action');
    $assert_session->buttonExists('Apply to selected items')->press();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('No access to execute Delete content on the Content Article for user with break permission.');
    $assert_session->pageTextContains('Are you sure you want to delete');
    // Confirm deletion.
    $this->assertEmpty(\Drupal::service(ReleaseRecorder::class)->getReleasedEntities());
    $this->submitForm([], 'Delete');
    $released_entities = \Drupal::service(ReleaseRecorder::class)->getReleasedEntities();
    $this->assertCount(1, $released_entities);
    $this->assertSame([
      'entity_id' => $article1->id(),
      'langcode' => 'und',
      'form_op' => '*',
      'entity_type' => $article1->getEntityTypeId(),
    ], $released_entities[0]);
    $assert_session->pageTextContains('Deleted 1 content item.');
    $this->drupalGet("node/{$article1->id()}");
    $assert_session->statusCodeEquals(404);
    $this->drupalGet("node/{$article2->id()}");
    $assert_session->statusCodeEquals(200);

    // Test user2 (with break lock permission) can delete the locked article.
    $this->drupalLogin($user2);
    $this->drupalGet('admin/content');
    $assert_session->fieldExists('node_bulk_form[0]')->check();
    $assert_session->fieldExists('edit-action')->selectOption('node_delete_action');
    $assert_session->buttonExists('Apply to selected items')->press();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Are you sure you want to delete');
    $this->assertSame(1, $this->countLocks($admin));
    $this->assertEmpty(\Drupal::service(ReleaseRecorder::class)->getReleasedEntities());
    // Confirm deletion.
    $this->submitForm([], 'Delete');
    $released_entities = \Drupal::service(ReleaseRecorder::class)->getReleasedEntities();
    $this->assertCount(1, $released_entities);
    $this->assertSame([
      'entity_id' => $article2->id(),
      'langcode' => 'und',
      'form_op' => '*',
      'entity_type' => $article2->getEntityTypeId(),
    ], $released_entities[0]);
    $assert_session->pageTextContains('Deleted 1 content item.');
    $this->drupalGet("node/{$article2->id()}");
    $assert_session->statusCodeEquals(404);
    $this->assertSame(0, $this->countLocks($admin));
  }

}
