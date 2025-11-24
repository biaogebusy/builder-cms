<?php

declare(strict_types=1);

namespace Drupal\Tests\advancedqueue\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\plugin\PluginType\PluginType;

/**
 * Tests plugin module integration.
 *
 * @group advancedqueue
 */
class PluginModuleIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'advancedqueue',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('advancedqueue', ['advancedqueue']);
    $this->installConfig(['advancedqueue']);
  }

  /**
   * Tests that the plugin module can be installed and the integration works.
   */
  public function testPluginModuleIntegration(): void {
    $this->container->get('module_installer')->install(['plugin']);

    /** @var \Drupal\plugin\PluginType\PluginTypeManager $plugin_type_manager */
    $plugin_type_manager = $this->container->get('plugin.plugin_type_manager');
    $plugin_type = $plugin_type_manager->getPluginType('advancedqueue.backend');
    $this->assertInstanceOf(PluginType::class, $plugin_type);
  }

}
