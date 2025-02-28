<?php

namespace Drupal\Tests\dropzonejs\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests related to the DropzoneJS requirements checks.
 *
 * @group dropzonejs
 */
class DropzoneRequirementsTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'file',
    'user',
    'dropzonejs',
  ];

  /**
   * Tests that the dropzonejs element appears.
   */
  public function testRequirements() {
    \Drupal::moduleHandler()->loadInclude('dropzonejs', 'install');
    $requirements = dropzonejs_requirements('runtime');
    $this->assertEquals(REQUIREMENT_OK, $requirements['dropzonejs_library']['severity']);
    $this->assertEquals('Dropzone library found', (string) $requirements['dropzonejs_library']['title']);
    $this->assertStringContainsString('Library location: libraries/dropzone/', (string) $requirements['dropzonejs_library']['description']);
  }

}
