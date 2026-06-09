<?php

namespace Drupal\Tests\ai_external_moderation\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests enabling ai_external_moderation and its dependencies.
 *
 * @group ai_external_moderation
 */
class InstallAiExternalModerationTest extends KernelTestBase {

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
      \Drupal::service('module_installer')->install(['ai_external_moderation']);
      $this->assertTrue(\Drupal::service('module_handler')->moduleExists('ai_external_moderation'), 'The module is successfully installed.');
    }
    catch (\Exception $e) {
      $this->fail('The module could not be enabled: ' . $e->getMessage());
    }
  }

}
