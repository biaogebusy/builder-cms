<?php

namespace Drupal\Tests\dropzonejs\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests related to the DropzoneJS element.
 *
 * @group dropzonejs
 */
class DropzoneJsElementTest extends KernelTestBase {

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
    'dropzonejs_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');

    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::create(['id' => RoleInterface::ANONYMOUS_ID, 'label' => 'editor']);
    $role->grantPermission('dropzone upload files');
    $role->save();
  }

  /**
   * Tests that the dropzonejs element appears.
   */
  public function testDropzoneJsElement() {
    $this->container->get('router.builder')->rebuild();
    $form = \Drupal::formBuilder()->getForm('\Drupal\dropzonejs_test\Form\DropzoneJsTestForm');
    $this->render($form);

    $xpath_base = "//div[contains(@class, 'form-item-dropzonejs')]";
    // Label.
    $this->assertEmpty($this->xpath("$xpath_base/label[text()='Not DropzoneJS element']"));
    $this->assertNotEmpty($this->xpath("$xpath_base/label[text()='DropzoneJS element']"));
    // Element where dropzonejs is attached to.
    $this->assertNotEmpty($this->xpath("$xpath_base/div[contains(@class, 'dropzone-enable')]"));
    // Uploaded files input.
    $this->assertNotEmpty($this->xpath("$xpath_base/input[contains(@data-drupal-selector, 'edit-dropzonejs-uploaded-files')]"));
    // Upload files path.
    $this->assertNotEmpty($this->xpath("$xpath_base/input[contains(@data-upload-path, '/dropzonejs/upload?token=')]"));

    // Js is attached.
    $new_js_path = $this->xpath("//html/body/script[contains(@src, 'libraries/dropzone/dropzone-min.js')]");
    $v6_js_path = $this->xpath("//html/body/script[contains(@src, 'libraries/dropzone/min/dropzone.min.js')]");
    $old_js_path = $this->xpath("//html/body/script[contains(@src, 'libraries/dropzone/dist/min/dropzone.min.js')]");

    $this->assertTrue($new_js_path || $v6_js_path || $old_js_path);
    $this->assertNotEmpty($this->xpath("//html/body/script[contains(@src, 'js/dropzone.integration.js')]"));
  }

}
