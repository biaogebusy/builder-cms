<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Node tests.
 *
 * @group content_lock
 */
class ContentLockHookTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'content_lock_hooks_test',
  ];

  /**
   * Test hook_content_lock_entity_lockable.
   */
  public function testContentLockEntityLockableHook() {
    $assert_session = $this->assertSession();

    $this->drupalCreateContentType(['type' => 'article']);
    $article = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Article 1',
    ]);

    $article2 = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Article 2',
    ]);

    $admin = $this->drupalCreateUser([
      'edit any article content',
      'delete any article content',
      'administer nodes',
      'administer content types',
      'administer content lock',
    ]);

    // We protect the bundle created.
    $this->drupalLogin($admin);
    $edit = [
      'node[bundles][article]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('This content is now locked against simultaneous editing.');

    $this->drupalGet("node/{$article2->id()}/edit");
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');
  }

}
