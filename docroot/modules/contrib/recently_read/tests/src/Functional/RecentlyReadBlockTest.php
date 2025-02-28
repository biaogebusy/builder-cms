<?php

namespace Drupal\Tests\recently_read\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Testing Recently Read Block.
 *
 * @group block
 * @group recently_read
 */
class RecentlyReadBlockTest extends BrowserTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}.
   */
  protected $defaultTheme = 'bartik';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'node',
    'block',
    'user',
    'recently_read',
    'views',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  private $user;

  /**
   * Initial set up for test.
   */
  protected function setUp() {
    // Always call the parent setUp().
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'foo']);
    $this->drupalCreateContentType(['type' => 'bar']);
    $this->drupalCreateContentType(['type' => 'baz']);

    $this->config('recently_read.recently_read_type.node')
      ->set('types', ['foo', 'baz'])
      ->save();

    $this->drupalPlaceBlock('views_block:recently_read_content-block_1', ['id' => 'recently_read_content', 'items_per_page' => 4]);
  }

  /**
   * Tests Recently read block for authenticated and anonymous user.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testRecentlyReadBlock() {
    // 0. create user & login
    // 1. visit few nodes.
    // 2. check recently read content block.
    // $this->fail('Recently read has failed you.');.
    $this->user = $this->DrupalCreateUser(['access content']);
    $this->drupalLogin($this->user);
    $node1 = $this->recentlyReadNodeCreate([
      'title' => t('TestNode1'),
      'type' => 'foo',
    ]);
    $node2 = $this->recentlyReadNodeCreate([
      'title' => t('TestNode2'),
      'type' => 'foo',
    ]);

    $node3 = $this->recentlyReadNodeCreate([
      'title' => t('TestNode3'),
      'type' => 'bar',
    ]);
    $node4 = $this->recentlyReadNodeCreate([
      'title' => t('TestNode4'),
      'type' => 'bar',
    ]);
    $node5 = $this->recentlyReadNodeCreate([
      'title' => t('TestNode5'),
      'type' => 'baz',
    ]);
    $node6 = $this->recentlyReadNodeCreate([
      'title' => t('TestNode6'),
      'type' => 'baz',
    ]);

    $this->drupalGet($node1->toUrl());
    $this->drupalGet($node2->toUrl());
    $this->drupalGet($node3->toUrl());
    $this->drupalGet($node4->toUrl());
    $this->drupalGet($node5->toUrl());
    $this->drupalGet($node6->toUrl());

    $this->drupalGet('/user');

    $session = $this->assertSession();

    $session->pageTextContains('Recently read content');
    $session->pageTextNotContains('TestNode3');
    $session->linkExists('TestNode6');

    // Check if recently read content gets replaced by new content.
    $node7 = $this->recentlyReadNodeCreate([
      'title' => t('TestNode7'),
      'type' => 'foo',
    ]);
    $node8 = $this->recentlyReadNodeCreate([
      'title' => t('TestNode8'),
      'type' => 'foo',
    ]);

    $this->drupalGet($node7->toUrl());
    $this->drupalGet($node8->toUrl());

    $session->linkExists('TestNode8');

    $this->drupalLogout();
    $session->pageTextNotContains('TestNode5');

    // Anonymous user.
    $this->drupalGet($node1->toUrl());
    $this->drupalGet($node2->toUrl());
    $this->drupalGet($node3->toUrl());
    $this->drupalGet($node4->toUrl());
    $this->drupalGet($node5->toUrl());
    $this->drupalGet($node6->toUrl());

    $this->drupalGet('/user');

    $session->pageTextContains('Recently read content');
    $session->pageTextNotContains('TestNode3');
    $session->linkExists('TestNode6');

    $this->drupalGet($node7->toUrl());
    $this->drupalGet($node8->toUrl());

    $session->linkExists('TestNode8');

  }

  /**
   * Helper method for creating nodes.
   */
  protected function recentlyReadNodeCreate($settings) {
    $this->drupalCreateNode($settings);
    if (isset($settings['title'])) {
      return $this->drupalGetNodeByTitle($settings['title']);
    }
    return NULL;
  }

}
