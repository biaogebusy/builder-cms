<?php

namespace Drupal\Tests\lightning_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lightning_core\UpdateManager;
use Prophecy\Argument;

/**
 * @group lightning_core
 */
class HooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lightning_core', 'user'];

  public function testModulesInstalled() {
    $update_manager = $this->prophesize(UpdateManager::class);
    $update_manager->getVersion(Argument::any())->willReturn('1.0.0');
    $this->container->set('lightning.update_manager', $update_manager->reveal());

    lightning_core_modules_installed(['foo', 'bar']);

    // The stored versions should be sorted by key.
    $expected_versions = [
      'bar' => '1.0.0',
      'foo' => '1.0.0',
    ];
    $this->assertSame($expected_versions, $this->config('lightning_core.versions')->get());
  }

}
