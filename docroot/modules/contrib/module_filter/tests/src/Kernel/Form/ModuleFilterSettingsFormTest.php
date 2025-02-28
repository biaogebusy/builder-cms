<?php

namespace Drupal\Tests\module_filter\Kernel\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\module_filter\Form\ModuleFilterSettingsForm;

/**
 * Tests the Module Filter settings form.
 *
 * @group module_filter
 */
class ModuleFilterSettingsFormTest extends KernelTestBase {

  /**
   * The ModuleFilter form object under test.
   *
   * @var \Drupal\module_filter\Form\ModuleFilterSettingsForm
   */
  protected $moduleFilterSettingsForm;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->moduleFilterSettingsForm = new ModuleFilterSettingsForm(
      $this->container->get('config.factory'),
      $this->container->get('config.typed')
    );
  }

  /**
   * Tests for \Drupal\module_filter\Form\ModuleFilterSettingsForm.
   */
  public function testModuleFilterSettingsForm() {
    $this->assertInstanceOf(FormInterface::class, $this->moduleFilterSettingsForm);

    $id = $this->moduleFilterSettingsForm->getFormId();
    $this->assertEquals('module_filter_settings_form', $id);

    $method = new \ReflectionMethod(ModuleFilterSettingsForm::class, 'getEditableConfigNames');
    $method->setAccessible(TRUE);

    $name = $method->invoke($this->moduleFilterSettingsForm);
    $this->assertEquals(['module_filter.settings'], $name);
  }

}
