<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Views tests.
 *
 * @group content_lock
 */
class ContentLockViewsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'node',
    'views',
    'views_ui',
    'content_lock',
  ];

  /**
   * Test Content Lock view display.
   */
  public function testViewDisplay() {
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalCreateContentType(['type' => 'article']);
    $admin = $this->drupalCreateUser([
      'access content overview',
      'administer content lock',
      'administer content types',
      'administer nodes',
      'administer views',
      'create article content',
      'delete any article content',
      'edit any article content',
    ]);

    $user1 = $this->drupalCreateUser([
      'access content overview',
      'create article content',
      'edit any article content',
      'delete any article content',
      'access content',
      'administer nodes',
      'break content lock',
    ]);

    $this->drupalLogin($admin);

    // Visit the content listing view to build the views cache.
    $this->drupalCreateNode(['type' => 'article', 'title' => 'Article 1']);
    $this->drupalCreateNode(['type' => 'article', 'title' => 'Article 2']);
    $this->drupalGet('/admin/content');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '#views-form-content-page-1', 'Article 1');
    $this->assertSession()->elementTextContains('css', '#views-form-content-page-1', 'Article 2');
    $this->assertSession()->linkNotExists('Locked content');

    // Test that the view throws a 403 if no entity is configured.
    $this->drupalGet('/admin/content/locked-content');
    $this->assertSession()->statusCodeEquals(403);

    // Add an entity to be locked.
    $edit = [
      'node[bundles][article]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    // Lock some content.
    $this->drupalGet('node/1/edit');
    $this->drupalGet('node/2/edit');

    // Verify view is still accessible and lists the locked node.
    $this->drupalGet('/admin/content');
    $this->clickLink('Locked content');
    $this->assertSession()->addressEquals('admin/content/locked-content');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '#views-form-locked-content-page-1', 'Article 1');
    $this->assertSession()->elementTextContains('css', '#views-form-locked-content-page-1', 'Article 2');

    // Unlock the first article as admin.
    $this->drupalGet('node/1/edit');
    $this->getSession()->getPage()->clickLink('edit-unlock');
    $this->getSession()->getPage()->pressButton('Confirm break lock');

    // Verify view updates.
    $this->drupalGet('admin/content/locked-content');
    $this->assertSession()->elementTextNotContains('css', '#views-form-locked-content-page-1', 'Article 1');
    $this->assertSession()->elementTextContains('css', '#views-form-locked-content-page-1', 'Article 2');
    $this->assertSession()->elementTextNotContains('css', '#views-form-locked-content-page-1', $user1->getDisplayName());

    // Login as a different user to break the lock of the second article.
    $this->drupalLogin($user1);
    $this->drupalGet('node/2/edit');
    $this->getSession()->getPage()->clickLink('Break lock');
    $this->getSession()->getPage()->pressButton('Confirm break lock');

    // Verify view updates. Article 2 should appear as the lock was taken over
    // by $user1.
    $this->drupalGet('admin/content/locked-content');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextNotContains('css', '#views-form-locked-content-page-1', 'Article 1');
    $this->assertSession()->elementTextContains('css', '#views-form-locked-content-page-1', 'Article 2');
    $this->assertSession()->elementTextContains('css', '#views-form-locked-content-page-1', $user1->getDisplayName());

    // Test bulk action.
    $this->getSession()->getPage()->checkField('node_bulk_form[0]');
    $this->submitForm(['edit-action' => 'node_break_lock_action'], 'Apply to selected items');
    $this->assertSession()->addressEquals('admin/content/locked-content');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextNotContains('css', '#views-form-locked-content-page-1', 'Article 1');

    // Test trying to use a view cache plugin.
    $this->drupalLogin($admin);
    $this->drupalGet('admin/structure/views/view/locked_content');
    $link = $this->getSession()->getPage()->findLink('views-page-1-cache');
    $this->assertSame('None', $link->getText());
    $link->click();
    $this->submitForm(['cache[type]' => 'tag'], 'Apply');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('The selected caching mechanism does not work with views including content lock information. The selected caching mechanism was changed to none accordingly for the view Locked content.');
    $this->assertSession()->pageTextContains('The view Locked content has been saved.');
    $link = $this->getSession()->getPage()->findLink('views-page-1-cache');
    $this->assertSame('None', $link->getText());
  }

}
