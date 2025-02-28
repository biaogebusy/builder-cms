<?php

namespace Drupal\Tests\entity_print_views\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * Test printing a view.
 *
 * @group entity_print_views
 */
class PrintViewsTest extends BrowserTestBase {

  /**
   * Modules to enable for the test.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'user',
    'views',
    'entity_print_test',
    'entity_print_views',
    'entity_print_views_test_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An array of nodes.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->nodes = [
      $this->drupalCreateNode(),
      $this->drupalCreateNode(),
      $this->drupalCreateNode(),
    ];

    // Set the test engine for PDF and grant the permission to use it to anon
    // users.
    $config = $this->container->get('config.factory')->getEditable('entity_print.settings');
    $config
      ->set('print_engines.pdf_engine', 'testprintengine')
      ->save();
    user_role_grant_permissions(Role::ANONYMOUS_ID, [
      'access content',
      'entity print views access',
      'administer nodes',
    ]);
  }

  /**
   * Test the basic view usages with exposed filters and contextual arguments.
   */
  public function testViewPdfLink() {
    $assert = $this->assertSession();

    // Visit the view and ensure all nodes are displayed.
    $this->drupalGet('/my-test-view');
    foreach ($this->nodes as $node) {
      $assert->pageTextContains($node->label());
    }
    // Assert the View PDF link.
    $assert->linkExists('View PDF');

    // Clicking the link shows all results using the test pdf engine.
    $this->clickLink('View PDF');
    $assert->pageTextContains('Using testprintengine');
    foreach ($this->nodes as $node) {
      $assert->pageTextContains($node->label());
    }

    // Use the exposed filter and ensure it's filtered.
    $this->drupalGet('/my-test-view');
    $this->submitForm(['title' => $this->nodes[0]->label()], 'Apply');
    $assert->pageTextContains($this->nodes[0]->label());
    $assert->pageTextNotContains($this->nodes[1]->label());
    $assert->pageTextNotContains($this->nodes[2]->label());

    // Clicking the link shows only the filtered node using the pdf engine.
    $this->clickLink('View PDF');
    $assert->pageTextContains('Using testprintengine');
    $assert->pageTextContains($this->nodes[0]->label());
    $assert->pageTextNotContains($this->nodes[1]->label());
    $assert->pageTextNotContains($this->nodes[2]->label());

    // Filter with a contextual args and assert just the one node.
    $this->drupalGet('/my-test-view/' . $this->nodes[1]->id());
    $assert->pageTextContains($this->nodes[1]->label());
    $assert->pageTextNotContains($this->nodes[0]->label());
    $assert->pageTextNotContains($this->nodes[2]->label());

    // Click the link and ensure we get the correct PDF results.
    $this->clickLink('View PDF');
    $assert->pageTextContains('Using testprintengine');
    $assert->pageTextContains($this->nodes[1]->label());
    $assert->pageTextNotContains($this->nodes[0]->label());
    $assert->pageTextNotContains($this->nodes[2]->label());
  }

}
