<?php

namespace Drupal\Tests\ai_api_explorer\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests enabling ai_api_explorer and its dependencies.
 *
 * @group ai_api_explorer
 */
class InstallAiApiExplorerTest extends KernelTestBase {

  /**
   * Modules to enable before running the tests.
   *
   * @var array
   */
  protected static $modules = ['system', 'user', 'ai'];

  /**
   * Tests if the module installs successfully.
   */
  public function testModuleCanBeEnabled() {

    try {
      // Try to enable the module.
      \Drupal::service('module_installer')->install(['ai_api_explorer']);
      $this->assertTrue(\Drupal::service('module_handler')->moduleExists('ai_api_explorer'), 'The module is successfully installed.');
    }
    catch (\Exception $e) {
      $this->fail('The module could not be enabled: ' . $e->getMessage());
    }
  }

}
