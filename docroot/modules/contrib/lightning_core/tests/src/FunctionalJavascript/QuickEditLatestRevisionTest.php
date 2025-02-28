<?php

namespace Drupal\Tests\lightning_core\FunctionalJavascript;

use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\quickedit\FunctionalJavascript\QuickEditJavascriptTestBase;

/**
 * Tests that Quick Edit is available on the latest revision of an entity.
 *
 * @group lightning_core
 */
class QuickEditLatestRevisionTest extends QuickEditJavascriptTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'content_moderation',
    'lightning_core',
    'node',
  ];

  /**
   * Tests that Quick Edit is available on the latest revision of an entity.
   */
  public function testQuickEditIsAvailableForLatestRevision(): void {
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalCreateContentType(['type' => 'page']);
    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'page');

    $node = $this->drupalCreateNode([
      'type' => 'page',
      'moderation_state' => 'draft',
      'body' => "Never did this before...",
      'uid' => $this->rootUser->id(),
    ]);
    $node->body->value = "...but it's published now!";
    $node->moderation_state->value = 'published';
    $node->save();
    $node->body->value = "Back to the ol' drawing board.";
    $node->moderation_state->value = 'draft';
    $node->save();

    $assert_session = $this->assertSession();
    $this->drupalLogin($this->rootUser);
    $this->drupalGet($node->toUrl());
    $assert_session->pageTextContains("...but it's published now!");
    $this->getSession()->getPage()->clickLink('Latest version');
    $assert_session->pageTextContains("Back to the ol' drawing board.");
    $this->awaitQuickEditForEntity('node', $node->id());
  }

}
