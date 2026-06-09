<?php

namespace Drupal\Tests\ai_ckeditor\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests enabling ai_ckeditor and its dependencies.
 *
 * @group ai_ckeditor
 */
class InstallAiCKEditorTest extends KernelTestBase {

  /**
   * Modules to enable before running the tests.
   *
   * @var array
   */
  protected static $modules = ['system', 'user', 'filter', 'editor', 'ckeditor5', 'ai'];

  /**
   * Tests if the module installs successfully.
   */
  public function testModuleCanBeEnabled() {

    try {
      // Try to enable the module.
      \Drupal::service('module_installer')->install(['ai_ckeditor']);
      $this->assertTrue(\Drupal::service('module_handler')->moduleExists('ai_ckeditor'), 'The module is successfully installed.');
    }
    catch (\Exception $e) {
      $this->fail('The module could not be enabled: ' . $e->getMessage());
    }
  }

}
