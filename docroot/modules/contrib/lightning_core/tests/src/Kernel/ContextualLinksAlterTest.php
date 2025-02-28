<?php

namespace Drupal\Tests\lightning_core\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * @covers lightning_core_contextual_links_plugins_alter
 *
 * @group lightning_core
 */
class ContextualLinksAlterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'contextual',
    'lightning_core',
    'node',
    'taxonomy',
  ];

  /**
   * Tests that dynamic contextual links are defined correctly.
   */
  public function testAlteredContextualLinks(): void {
    $links = [
      'block_content.block_edit_latest_version',
      'entity.taxonomy_term.latest_version_edit_form',
      'entity.node.latest_version_edit_form',
    ];
    foreach ($links as $link_id) {
      /** @var \Drupal\Core\Menu\ContextualLinkInterface $link */
      $link = $this->container->get('plugin.manager.menu.contextual_link')
        ->createInstance($link_id);

      $this->assertNotEmpty($link->getTitle());
      $this->assertNotEmpty($link->getRouteName());
      $this->assertNotEmpty($link->getGroup());
      $this->assertIsArray($link->getOptions());
      $this->assertIsInt($link->getWeight());
    }
  }

}
