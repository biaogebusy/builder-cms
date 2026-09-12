<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests content lock and user cancelling.
 *
 * @group content_lock
 */
#[RunTestsInSeparateProcesses]
class ContentLockUserCancelTest extends ContentLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'article']);
  }

  /**
   * Tests simultaneous edit on test entity.
   *
   * @testWith ["user_cancel_delete"]
   *           ["user_cancel_block"]
   */
  public function testContentLockEntity(string $method): void {

    // We protect the bundle created.
    $this->drupalLogin($this->admin);
    $edit = [
      'node[bundles][*]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    // We lock entity.
    $user = $this->drupalCreateUser(['create article content', 'edit own article content']);
    $this->drupalLogin($user);

    $article = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'This is a test!',
      'uid' => $user->id(),
    ]);

    // Edit an entity without saving.
    $this->drupalGet($article->toUrl('edit-form'));
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Cancel user1 with a user that cannot break locks. Being able to delete
    // users overrides the need for the break lock permission.
    $this->drupalLogin($this->createUser(['administer users']));
    $this->drupalGet($article->toUrl());
    $this->assertSession()->pageTextContains('This is a test!');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet($user->toUrl('edit-form'));
    $this->clickLink('Cancel account');
    $this->assertSession()->fieldExists('user_cancel_method')->selectOption($method);
    $this->assertSame(1, $this->countLocks($user));
    $this->assertSession()->buttonExists('Confirm')->press();
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextMatchesCount(1, '/Account .* has been (deleted|disabled)./');

    // The users content has been deleted.
    if ($method === 'user_cancel_delete') {
      $this->drupalGet($article->toUrl());
      $this->assertSession()->pageTextNotContains('This is a test!');
      $this->assertSession()->statusCodeEquals(404);
    }

    // Count that there are no locks.
    $this->assertSame(0, $this->countLocks($user));
  }

}
