<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;

/**
 * Node tests.
 *
 * @group content_lock
 */
class ContentLockNodeTest extends BrowserTestBase {

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
   * Test simultaneous edit on content type article.
   */
  public function testContentLockNode() {

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

    // We protect the bundle created.
    $this->drupalLogin($admin);
    $edit = [
      'node[bundles][article]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    // We lock article1.
    $this->drupalLogin($user1);
    // Edit a node without saving.
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit article1.
    $this->drupalLogin($user2);
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains("This content is being edited by the user {$user1->getDisplayName()} and is therefore locked to prevent other users changes.");
    $assert_session->linkExists('Break lock');
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));
    $textarea = $assert_session->elementExists('css', 'textarea#edit-body-0-value');
    $this->assertTrue($textarea->hasAttribute('disabled'));

    // We save article 1 and unlock it.
    $this->drupalLogin($user1);
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->drupalGet('/node/' . $article->id() . '/edit');
    $this->submitForm([], 'Save');

    // We lock article1 with user2.
    $this->drupalLogin($user2);
    // Edit a node without saving.
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit article1.
    $this->drupalLogin($user1);
    $this->drupalGet("node/{$article->id()}/edit");
    $assert_session->pageTextContains("This content is being edited by the user {$user2->getDisplayName()} and is therefore locked to prevent other users changes.");
    $assert_session->linkNotExists('Break lock');
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

}
