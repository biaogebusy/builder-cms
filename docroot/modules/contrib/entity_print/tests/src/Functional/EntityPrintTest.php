<?php

namespace Drupal\Tests\entity_print\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * Test file.
 *
 * @group entity_print
 */
class EntityPrintTest extends BrowserTestBase {

  /**
   * An array of modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'user',
    'node',
    'entity_print_test',
    'entity_print_views',
    'entity_print_views_test_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The node we're printing.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->createContentType(['type' => 'page']);
    $this->node = $this->createNode();

    // Set the default engine.
    $config = $this->container->get('config.factory')->getEditable('entity_print.settings');
    $config
      ->set('print_engines.pdf_engine', 'print_exception_engine')
      ->save();
    user_role_grant_permissions(Role::ANONYMOUS_ID, [
      'access content',
      'bypass entity print access',
      'entity print views access',
      'administer nodes',
    ]);
  }

  /**
   * Ensure exceptions are handled correctly.
   */
  public function testExceptionOnRender() {
    $this->drupalGet('/entityprint/pdf/node/1');

    $this->assertSession()->elementContains('css', '[aria-label="Error message"]', 'Error generating document: Exception thrown by PrintExceptionEngine');
    $this->assertSession()->pageTextNotContains('The website encountered an unexpected error');
  }

  /**
   * Exceptions during the rendering of the View PDF should be handled.
   */
  public function testViewsExceptionOnRender() {
    $this->drupalGet('/my-test-view');
    $this->clickLink('View PDF');

    $this->assertSession()->elementContains('css', '[aria-label="Error message"]', 'Error generating document: Exception thrown by PrintExceptionEngine');
    $this->assertSession()->pageTextNotContains('The website encountered an unexpected error');
  }

  /**
   * Regular 404s should work.
   */
  public function testPageNotFoundException() {
    $this->drupalGet('/this-page-does-not-exist');
    $this->assertSession()->pageTextNotContains('The website encountered an unexpected error');
    $this->assertSession()->statusCodeEquals(404);
  }


}
