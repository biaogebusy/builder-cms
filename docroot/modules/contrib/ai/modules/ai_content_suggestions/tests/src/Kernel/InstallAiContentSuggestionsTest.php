<?php

namespace Drupal\Tests\ai_content_suggestions\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests enabling ai_content_suggestions and its dependencies.
 *
 * @group ai_content_suggestions
 */
class InstallAiContentSuggestionsTest extends KernelTestBase {

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
      \Drupal::service('module_installer')->install(['ai_content_suggestions']);
      $this->assertTrue(\Drupal::service('module_handler')->moduleExists('ai_content_suggestions'), 'The module is successfully installed.');
    }
    catch (\Exception $e) {
      $this->fail('The module could not be enabled: ' . $e->getMessage());
    }
  }

}
