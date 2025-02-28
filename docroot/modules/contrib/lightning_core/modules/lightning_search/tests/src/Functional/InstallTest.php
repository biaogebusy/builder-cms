<?php

namespace Drupal\Tests\lightning_search\Functional;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests Lightning Search's install-time logic.
 *
 * @group lightning_search
 * @group lightning_core
 */
class InstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * Tests installing Lightning Search.
   */
  public function testInstall(): void {
    // Create a content type before installing, which should be added to the
    // index automatically.
    $node_type = $this->drupalCreateContentType();

    $this->container->get('module_installer')->install(['lightning_search']);

    // Search API DB should be installed, and the database server should have
    // been created and associated with the content index.
    $this->assertInstanceOf(Server::class, Server::load('database'));
    $index = Index::load('content');
    $this->assertInstanceOf(Index::class, $index);
    $this->assertTrue($index->status());
    $this->assertSame('database', $index->getServerId());
    // The index's rendered entity field should be aware of the existing content
    // type.
    // @see lightning_search_node_type_insert()
    $field = $index->getField('rendered')->getConfiguration();
    $this->assertArrayHasKey($node_type->id(), $field['view_mode']['entity:node']);

    // If we disable the index and add another content type, it should not cause
    // a problem.
    $index->disable()->save();
    $this->drupalCreateContentType();
  }

}
