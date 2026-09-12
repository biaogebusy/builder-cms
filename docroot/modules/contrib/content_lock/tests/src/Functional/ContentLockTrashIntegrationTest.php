<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

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
class ContentLockTrashIntegrationTest extends BrowserTestBase {
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
    'trash',
  ];

  /**
   * Test simultaneous edit on content type article.
   */
  public function testContentLockNode(): void {

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
      'administer trash',
      'restore node entities',
    ]);

    // We protect the bundle created.
    $this->drupalLogin($admin);
    $edit = [
      'node[bundles][article]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    // Rebuild router and container to ensure environment and test runner are
    // aligned.
    $this->rebuildAll();

    // Edit a node without saving.
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Edit a node without saving.
    $this->drupalGet("node/{$article->id()}/delete");
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->submitForm([], 'Delete');
    $assert_session->pageTextContains('has been deleted.');

    $this->drupalGet("node/{$article->id()}/restore", ['query' => ['in_trash' => 1]]);
    $assert_session->pageTextNotContains('simultaneous editing.');
    // Ensure restoring an entity does not lock it.
    $this->assertFalse(\Drupal::service('content_lock')->fetchLock($article));
    $this->submitForm([], 'Confirm');

    $this->drupalGet("node/{$article->id()}/edit");
    $this->assertObjectHasProperty('entity_id', \Drupal::service('content_lock')->fetchLock($article));
  }

}
