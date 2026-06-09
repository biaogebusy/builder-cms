<?php

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests enabling ai_translate and its dependencies.
 *
 * @group ai_translate
 */
class InstallAiTranslateTest extends KernelTestBase {

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
      \Drupal::service('module_installer')->install(['ai_translate']);
      $this->assertTrue(\Drupal::service('module_handler')->moduleExists('ai_translate'), 'The module is successfully installed.');
    }
    catch (\Exception $e) {
      $this->fail('The module could not be enabled: ' . $e->getMessage());
    }
  }

}
