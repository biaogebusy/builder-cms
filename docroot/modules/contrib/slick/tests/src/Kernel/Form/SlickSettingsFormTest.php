<?php

namespace Drupal\Tests\slick\Kernel\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\slick\Traits\SlickKernelTrait;
use Drupal\slick_ui\Form\SlickSettingsForm;

/**
 * Tests the Slick UI settings form.
 */
/**
 * A D12 compat, please update or ignore.
 *
 * @phpstan-ignore-next-line
 */
#[Group('blazy')]
/**
 * A D12 compat, please update or ignore.
 *
 * @phpstan-ignore-next-line
 */
#[RunTestsInSeparateProcesses]
class SlickSettingsFormTest extends KernelTestBase {

  use SlickKernelTrait;

  /**
   * The slick settings form object under test.
   *
   * @var \Drupal\slick_ui\Form\SlickSettingsForm
   */
  protected $slickSettingsForm;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'file',
    'image',
    'media',
    'user',
    'blazy',
    'slick',
    'slick_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('user');

    $this->installConfig('image');
    $this->installConfig('media');
    $this->installConfig('system');

    $this->installConfig([
      'blazy',
      'slick',
    ]);

    $this->slickManager = $this->container->get('slick.manager');

    $this->slickSettingsForm = SlickSettingsForm::create($this->container);
  }

  /**
   * Tests for \Drupal\slick_ui\Form\SlickSettingsForm.
   */
  public function testSlickSettingsForm() {
    // Emulate a form state of a submitted form.
    $form_state = (new FormState())->setValues([
      'slick_css'  => TRUE,
      'module_css' => TRUE,
    ]);

    $this->assertInstanceOf(FormInterface::class, $this->slickSettingsForm);
    $this->assertTrue($this->slickManager->configFactory()->get('slick.settings')->get('slick_css'));

    $id = $this->slickSettingsForm->getFormId();
    $this->assertEquals('slick_settings_form', $id);

    $method = new \ReflectionMethod(SlickSettingsForm::class, 'getEditableConfigNames');
    $method->setAccessible(TRUE);

    $name = $method->invoke($this->slickSettingsForm);
    $this->assertEquals(['slick.settings'], $name);

    $form = $this->slickSettingsForm->buildForm([], $form_state);
    $this->slickSettingsForm->submitForm($form, $form_state);
  }

}
