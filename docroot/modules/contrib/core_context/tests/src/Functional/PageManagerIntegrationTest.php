<?php

namespace Drupal\Tests\core_context\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @group core_context
 *
 * @requires module page_manager
 */
class PageManagerIntegrationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'core_context',
    'core_context_test',
    'node',
    'page_manager',
  ];

  /**
   * Tests Core Context's integration with Page Manager.
   */
  public function test() {
    $node = $this->drupalCreateNode([
      'type' => $this->drupalCreateContentType()->id(),
    ]);

    $account = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($account);
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextContains('Powered by Drupal');
  }

}
