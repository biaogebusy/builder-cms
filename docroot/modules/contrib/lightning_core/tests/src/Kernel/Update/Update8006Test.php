<?php

namespace Drupal\Tests\lightning_core\Kernel\Update;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lightning_core\UpdateManager;

/**
 * @group lightning_core
 */
class Update8006Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lightning_core', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  public function testUpdate() {
    $this->container->get('module_handler')
      ->loadInclude('lightning_core', 'install');
    lightning_core_update_8006();

    $config = $this->container->get('config.factory')
      ->get('lightning.versions');

    foreach (static::$modules as $module) {
      $this->assertSame(UpdateManager::VERSION_UNKNOWN, $config->get($module));
    }
  }

}
