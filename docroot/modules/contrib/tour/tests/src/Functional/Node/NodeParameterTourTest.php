<?php

namespace Drupal\Tests\tour\Functional\Node;

use Drupal\Tests\tour\Functional\TourTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Node parameters tour.
 */
#[Group('tour')]
class NodeParameterTourTest extends TourTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'tour_node_test'];

  /**
   * {@inheritdoc}
   */
  protected array $permissions = [
    'access administration pages',
    'bypass node access',
  ];

  /**
   * Tests node parameters for tour.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testNodeParametersTour(): void {
    // Create a page node.
    $this->drupalCreateContentType(['type' => 'page']);
    // Create a page article.
    $this->drupalCreateContentType(['type' => 'article']);
    // Create a blog content type.
    $this->drupalCreateContentType(['type' => 'blog']);

    $this->drupalLogin($this->adminUser);

    // Node 1 should have tour.
    $node = $this->drupalCreateNode(['type' => 'page']);

    $this->drupalGet($node->toUrl()->toString() . '/edit');
    $this->assertTourTips();

    // Node 2, should not have tour.
    $node = $this->drupalCreateNode(['type' => 'article']);

    $this->drupalGet($node->toUrl()->toString() . '/edit');
    $this->assertTourTips([], TRUE);

    // Node 3, should have tour.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Article',
    ]);

    $this->drupalGet($node->toUrl()->toString() . '/edit');
    $this->assertTourTips();

    // Node 4, should have tour.
    $node = $this->drupalCreateNode([
      'type' => 'blog',
      'title' => 'Blog',
    ]);

    $this->drupalGet($node->toUrl()->toString() . '/edit');
    $this->assertTourTips();
  }

}
