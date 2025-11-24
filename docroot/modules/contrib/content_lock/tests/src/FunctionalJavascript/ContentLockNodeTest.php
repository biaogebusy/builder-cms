<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\FunctionalJavascript;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests JS locking for Nodes.
 *
 * @group content_lock
 */
class ContentLockNodeTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'ckeditor5',
    'content_lock',
  ];

  /**
   * Test forms on nodes when JS lock is enabled.
   */
  public function testContentLockNodeWithJsLock() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

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

    $this->drupalLogin($admin);
    $this->drupalGet('admin/config/content/content_lock');
    $this->click('#edit-entity-types-node');
    $this->click('#edit-node-settings-js-lock');
    $page->pressButton('Save configuration');

    // Lock the article page.
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Verify ckeditor5 field is disabled.
    $this->drupalLogin($user1);
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains("This content is being edited by the user {$admin->getDisplayName()} and is therefore locked to prevent other users changes.");
    $textarea = $assert_session->elementExists('css', 'textarea#edit-body-0-value');
    $this->assertTrue($textarea->hasAttribute('disabled'));
  }

}
