<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_lock\Tools\LogoutTrait;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests content_lock integration with content_moderation.
 *
 * @group content_lock
 */
#[RunTestsInSeparateProcesses]
class ContentLockModerationIntegrationTest extends BrowserTestBase {

  use ContentModerationTestTrait;
  use LogoutTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'content_lock',
    // Tests Drupal\content_lock\Hook\FormAlter::disableForm() processes the
    // form.
    'content_lock_test',
    'content_moderation',
    'workflows',
  ];

  /**
   * Tests that moderation state controls are disabled when content is locked.
   */
  public function testModerationStateDisabledWhenLocked(): void {
    $assert_session = $this->assertSession();

    $this->drupalCreateContentType(['type' => 'article']);
    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'article');

    $admin = $this->drupalCreateUser([
      'administer nodes',
      'administer content types',
      'administer content lock',
    ]);
    $user1 = $this->drupalCreateUser([
      'create article content',
      'edit any article content',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'view any unpublished content',
      'view latest version',
    ]);
    $user2 = $this->drupalCreateUser([
      'create article content',
      'edit any article content',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'view any unpublished content',
      'view latest version',
    ]);

    // Enable content lock for article.
    $this->drupalLogin($admin);
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm(['node[bundles][article]' => 1], 'Save configuration');

    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test article',
      'uid' => $user1->id(),
      'moderation_state' => 'draft',
    ]);

    $this->drupalLogin($user2);
    // user2 visits the node view to ensure the moderation form is present.
    $this->drupalGet("node/{$node->id()}");
    $assert_session->pageTextContains('Moderation state');

    $this->drupalLogin($user1);
    // user1 visits the edit page, acquiring the lock.
    $this->drupalGet("node/{$node->id()}/edit");
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // user1 should still be able to moderate the locked content because it is
    // their lock.
    $this->drupalGet("node/{$node->id()}");
    $assert_session->pageTextContains('Moderation state');

    // user2 visits the edit page while content is locked by user1.
    $this->drupalLogin($user2);
    $this->drupalGet("node/{$node->id()}/edit");
    $assert_session->pageTextContains("This content is being edited by the user {$user1->getDisplayName()} and is therefore locked to prevent changes by other users");

    // The moderation state select must be present and disabled.
    $moderation_select = $assert_session->fieldExists('moderation_state[0][state]');
    $this->assertTrue($moderation_select->hasAttribute('disabled'));

    // user2 visits the node view to ensure the moderation form is not present.
    $this->drupalGet("node/{$node->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Moderation state');
    $assert_session->pageTextContains('Test article');

    // Ensure that the system does not error when no entity is attached to the
    // content moderation form.
    $workflow = Workflow::load('editorial');
    // Remove all transitions from draft to another status.
    $workflow
      ->getTypePlugin()
      ->deleteTransition('create_new_draft')
      ->deleteTransition('publish');
    $workflow->save();
    // Update caches to reflect workflow changes.
    $this->rebuildAll();
    $this->drupalGet("node/{$node->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $assert_session->pageTextContains('Test article');
  }

}
