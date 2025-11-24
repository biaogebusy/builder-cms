<?php

namespace Drupal\Tests\panels\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Panels' implementation of hook_layout_alter().
 *
 * @group panels
 */
class LayoutAlterHookTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['layout_discovery', 'panels'];

  /**
   * Tests that Panels correctly modifies layout icons.
   */
  public function testIconPath() {
    /** @var \Drupal\Core\Layout\LayoutDefinition $layout */
    $layout = $this->container->get('plugin.manager.core.layout')
      ->getDefinition('layout_onecol');

    $layout_path = substr($layout->getIconPath(), strpos($layout->getIconPath(), 'panels'));
    $this->assertEquals('panels/layouts/layout_onecol/layout_onecol.png', $layout_path);
  }

}
