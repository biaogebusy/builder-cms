<?php

namespace Drupal\Tests\lightning_search\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Entity\View;

/**
 * Tests Lightning's out of the box search functionality.
 *
 * @group lightning_search
 * @group orca_public
 */
class SearchTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'lightning_search'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\views\ViewEntityInterface $view */
    $view = View::load('search');
    $display = &$view->getDisplay('default');
    $display['display_options']['cache'] = [
      'type' => 'none',
      'options' => [],
    ];
    $view->save();

    $this->drupalPlaceBlock('views_exposed_filter_block:search-page', [
      'visibility' => [
        'request_path' => [
          'pages' => '/search',
        ],
      ],
    ]);
  }

  /**
   * Tests that search appears where we expect and respects access restrictions.
   */
  public function testAnonymousSearch() {
    $node_type = $this->drupalCreateContentType()->id();

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => $node_type,
      'title' => 'Zombie 1',
      'body' => 'Zombie ipsum reversus ab viral inferno, nam rick grimes malum cerebro.',
    ]);
    $node->setUnpublished()->save();

    $node = Node::create([
      'type' => $node_type,
      'title' => 'Zombie 2',
      'body' => 'De carne lumbering animata corpora quaeritis.',
    ]);
    $node->setUnpublished()->save();

    $node = Node::create([
      'type' => $node_type,
      'title' => 'Zombie 3',
      'body' => 'Summus brains sit, morbo vel maleficia?',
    ]);
    $node->setPublished()->save();

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('/search');
    $assert_session->statusCodeEquals(200);
    $page->fillField('Keywords', 'zombie');
    $page->pressButton('Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->linkNotExists('Zombie 1');
    $assert_session->linkNotExists('Zombie 2');
    $assert_session->linkExists('Zombie 3');
  }

}
