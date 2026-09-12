<?php

namespace Drupal\Tests\entity_print\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\BrowserTestBase;

/**
 * Test printing a specific entity revision.
 *
 * @group entity_print
 */
class EntityPrintRevisionTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'entity_print_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The node under test.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * The revision id of the original, non-default, revision.
   *
   * @var int
   */
  protected $originalRevisionId;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);
    $this->node = $this->drupalCreateNode([
      'title' => 'Original title',
      'body' => [['value' => 'Original body', 'format' => 'plain_text']],
    ]);
    $this->originalRevisionId = (int) $this->node->getRevisionId();

    // Create a second revision so the first is no longer the default.
    $this->node->setTitle('Updated title');
    $this->node->body->value = 'Updated body';
    $this->node->setNewRevision(TRUE);
    $this->node->save();

    // Enable the "View PDF" link so it appears on rendered nodes.
    EntityViewDisplay::load('node.page.default')
      ->setComponent('entity_print_view_pdf', ['weight' => 0])
      ->save();

    // Add a PDF view mode, and show the body on it, so that printing the
    // node uses that view mode instead of "full". The node title is only
    // included in the rendered content for view modes other than "full".
    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'page',
      'mode' => 'pdf',
      'status' => TRUE,
    ])
      ->setComponent('body', ['label' => 'hidden', 'type' => 'text_default'])
      ->save();

    $account = $this->createUser([
      'bypass entity print access',
      'access content',
      'view all revisions',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * A revision id in the URL should print that revision's content.
   */
  public function testPrintRevision() {
    $assert = $this->assertSession();

    // With no revision id given the default (latest) revision is printed.
    $this->drupalGet('/print/pdf/node/' . $this->node->id() . '/debug');
    $assert->pageTextContains('Updated title');
    $assert->pageTextContains('Updated body');
    $assert->pageTextNotContains('Original title');
    $assert->pageTextNotContains('Original body');

    // With a revision id given, that revision is printed instead.
    $this->drupalGet('/print/pdf/node/' . $this->node->id() . '/' . $this->originalRevisionId . '/debug');
    $assert->pageTextContains('Original title');
    $assert->pageTextContains('Original body');
    $assert->pageTextNotContains('Updated title');
    $assert->pageTextNotContains('Updated body');
  }

  /**
   * Tests access to print a revision.
   */
  public function testPrintRevisionAccess() {
    // A non-existent revision id should not be accessible.
    $this->drupalGet('/print/pdf/node/' . $this->node->id() . '/999999/debug');
    $this->assertSession()->statusCodeEquals(403);

    // A revision id for a different node should not be accessible.
    $another_node = $this->drupalCreateNode([
      'title' => 'Another node',
    ]);
    $this->drupalGet('/print/pdf/node/' . $this->node->id() . '/' . $another_node->getRevisionId() . '/debug');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * The View PDF link should use the revision route for old revisions.
   */
  public function testViewPdfLinkOnRevision() {
    $assert = $this->assertSession();

    // On the current (default) revision the link has no revision id.
    $this->drupalGet($this->node->toUrl());
    $assert->linkByHrefExists('/print/pdf/node/' . $this->node->id());
    $assert->linkByHrefNotExists('/print/pdf/node/' . $this->node->id() . '/' . $this->originalRevisionId);

    // On the earlier, non-default revision the link includes the revision id.
    $this->drupalGet('node/' . $this->node->id() . '/revisions/' . $this->originalRevisionId . '/view');
    $assert->linkByHrefExists('/print/pdf/node/' . $this->node->id() . '/' . $this->originalRevisionId);
  }

}
