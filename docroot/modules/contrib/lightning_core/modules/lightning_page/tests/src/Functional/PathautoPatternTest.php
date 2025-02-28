<?php

namespace Drupal\Tests\lightning_page\Functional;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * @group lightning
 * @group lightning_core
 * @group lightning_page
 */
class PathautoPatternTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lightning_page',
    'pathauto',
  ];

  /**
   * Tests that Basic Page nodes are available at path '/[node:title]'.
   */
  public function testPagePattern() {
    $node = Node::create([
      'type' => 'page',
      'title' => 'Foo Bar',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $node->save();
    $this->drupalGet('/foo-bar');
    $this->assertSession()->pageTextContains('Foo Bar');
    $this->assertSession()->statusCodeEquals(200);
  }

}
