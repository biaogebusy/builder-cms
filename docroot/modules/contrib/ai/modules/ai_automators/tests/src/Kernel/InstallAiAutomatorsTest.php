<?php

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests enabling ai_automators and its dependencies.
 *
 * @group ai_automators
 */
class InstallAiAutomatorsTest extends KernelTestBase {

  /**
   * Modules to enable before running the tests.
   *
   * @var array
   */
  protected static $modules = ['system', 'file', 'user', 'ai', 'token'];

  /**
   * Tests if the module installs successfully.
   */
  public function testModuleCanBeEnabled() {

    try {
      // Try to enable the module.
      \Drupal::service('module_installer')->install(['ai_automators']);
      $this->assertTrue(\Drupal::service('module_handler')->moduleExists('ai_automators'), 'The module is successfully installed.');
    }
    catch (\Exception $e) {
      $this->fail('The module could not be enabled: ' . $e->getMessage());
    }
  }

}
