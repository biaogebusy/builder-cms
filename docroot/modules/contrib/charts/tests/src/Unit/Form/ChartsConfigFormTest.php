<?php

declare(strict_types=1);

namespace Drupal\Tests\charts\Unit\Form;

use Drupal\charts\Form\ChartsConfigForm;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the ChartsConfig Form class.
 *
 * @group charts
 * @coversDefaultClass \Drupal\charts\Form\ChartsConfigForm
 */
class ChartsConfigFormTest extends UnitTestCase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The config typed.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configTyped;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cacheTagsInvalidator;

  /**
   * The chart plugin manager.
   *
   * @var \Drupal\charts\ChartManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $chartPluginManager;

  /**
   * The chart type plugin manager.
   *
   * @var \Drupal\charts\TypeManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $chartTypePluginManager;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleExtensionList;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileSystem;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $messenger;

  /**
   * The form.
   *
   * @var \Drupal\charts\Form\ChartsConfigForm
   */
  protected $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $this->configFactory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $container->set('config.factory', $this->configFactory);

    $this->configTyped = $this->createMock('Drupal\Core\Config\TypedConfigManagerInterface');
    $container->set('config.typed', $this->configTyped);

    $this->cacheTagsInvalidator = $this->createMock('Drupal\Core\Cache\CacheTagsInvalidatorInterface');
    $container->set('cache_tags.invalidator', $this->cacheTagsInvalidator);

    $this->chartPluginManager = $this->createMock('Drupal\charts\ChartManager');
    $container->set('plugin.manager.charts', $this->chartPluginManager);

    $this->chartTypePluginManager = $this->createMock('Drupal\charts\TypeManager');
    $container->set('plugin.manager.charts_type', $this->chartTypePluginManager);

    $this->moduleExtensionList = $this->createMock('Drupal\Core\Extension\ModuleExtensionList');
    $container->set('extension.list.module', $this->moduleExtensionList);

    $this->fileSystem = $this->createMock('Drupal\Core\File\FileSystemInterface');
    $container->set('file_system', $this->fileSystem);

    $this->messenger = $this->createMock('Drupal\Core\Messenger\MessengerInterface');
    $container->set('messenger', $this->messenger);

    \Drupal::setContainer($container);

    $this->form = ChartsConfigForm::create($container);
  }

  /**
   * Tests the constructor.
   *
   * @covers ::__construct
   */
  public function testConstructor(): void {
    $form = new ChartsConfigForm(
      $this->configFactory,
      $this->configTyped,
      $this->cacheTagsInvalidator,
      $this->chartPluginManager,
      $this->chartTypePluginManager,
      $this->moduleExtensionList,
      $this->fileSystem
    );

    $this->assertInstanceOf(ChartsConfigForm::class, $form);
  }

  /**
   * Tests the constructor.
   *
   * @covers ::__construct
   */
  public function testConstructorMissingDependencies(): void {
    $form = new ChartsConfigForm(
      $this->configFactory,
      $this->configTyped,
      $this->cacheTagsInvalidator,
      NULL,
      NULL,
      NULL,
      NULL
    );

    $this->assertInstanceOf(ChartsConfigForm::class, $form);
  }

  /**
   * Tests the create method.
   *
   * @covers ::create
   */
  public function testCreate(): void {
    $container = \Drupal::getContainer();
    $form = ChartsConfigForm::create($container);
    $this->assertInstanceOf(ChartsConfigForm::class, $form);
  }

  /**
   * Tests getFormId().
   *
   * @covers ::getFormId
   */
  public function testGetFormId(): void {
    $this->assertEquals('charts_form_base', $this->form->getFormId());
  }

  /**
   * Tests getEditableConfigNames().
   *
   * @covers ::getEditableConfigNames
   */
  public function testGetEditableConfigNames(): void {
    // Make method accessible for testing.
    $reflection = new \ReflectionClass(ChartsConfigForm::class);
    $method = $reflection->getMethod('getEditableConfigNames');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->form);

    $this->assertEquals(['charts.settings'], $result);
  }

  /**
   * Tests buildForm().
   *
   * @covers ::buildForm
   */
  public function testBuildForm(): void {
    $settings = $this->createMock('Drupal\Core\Config\Config');
    $settings->expects($this->once())
      ->method('get')
      ->with('charts_default_settings')
      ->willReturn(['library' => 'library']);
    $this->configFactory->expects($this->once())
      ->method('getEditable')
      ->with('charts.settings')
      ->willReturn($settings);

    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');

    $form = $this->form->buildForm([], $form_state);

    $this->assertIsArray($form);
    $this->assertCount(6, $form);
    $this->assertArrayHasKey('help', $form);
    $this->assertArrayHasKey('settings', $form);
    $this->assertArrayHasKey('actions', $form);
    $this->assertArrayHasKey('reset_to_default', $form['actions']);
  }

  /**
   * Tests the validateForm() method.
   *
   * @covers ::validateForm
   */
  public function testValidateForm(): void {
    $form = [
      'settings' => [
        '#type' => 'charts_settings',
        '#used_in' => 'config_form',
        '#required' => TRUE,
        '#default_value' => [],
      ],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('settings')
      ->willReturn(['library' => '']);
    $form_state->expects($this->once())
      ->method('setError')
      ->with($form['settings'], $this->anything());

    $this->form->validateForm($form, $form_state);
  }

  /**
   * Tests the submitForm() method.
   *
   * @covers ::submitForm
   */
  public function testSubmitForm(): void {

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $settings_state = [
      'defaults' => 'defaults',
      'library' => 'chartjs',
      'type' => 'line',
      'display' => [
        'colors' => [
          ['color' => '#000000'],
          ['color' => '#FFFFFF'],
        ],
      ],
    ];
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('settings')
      ->willReturn($settings_state);

    $settings = $this->createMock('Drupal\Core\Config\Config');
    $settings->expects($this->exactly(2))
      ->method('set')
      ->willReturnSelf();
    $settings->expects($this->once())
      ->method('save')
      ->willReturnSelf();
    $settings->expects($this->once())
      ->method('getCacheTags')
      ->willReturn(['config:charts.settings']);

    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with(['config:charts.settings']);

    $this->configFactory->expects($this->once())
      ->method('getEditable')
      ->with('charts.settings')
      ->willReturn($settings);

    $this->chartPluginManager->expects($this->once())
      ->method('getDefinition')
      ->with('chartjs')
      ->willReturn(['provider' => 'charts']);

    $this->chartTypePluginManager->expects($this->once())
      ->method('getDefinition')
      ->with('line')
      ->willReturn(['provider' => 'charts']);

    $this->form->submitForm($form, $form_state);
  }

  /**
   * Tests the submitReset() method.
   *
   * @covers ::submitReset
   */
  public function testSubmitReset(): void {
    $real_path = realpath(__DIR__ . '/../../../..');
    $this->moduleExtensionList->expects($this->once())
      ->method('getPath')
      ->with('charts')
      ->willReturn($real_path);
    $config_path = $real_path . '/config/install/charts.settings.yml';
    $this->fileSystem->expects($this->once())
      ->method('realpath')
      ->with($config_path)
      ->willReturn($config_path);

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');

    $settings = $this->createMock('Drupal\Core\Config\Config');
    $settings->expects($this->exactly(2))
      ->method('set')
      ->willReturnSelf();
    $this->configFactory->expects($this->once())
      ->method('getEditable')
      ->with('charts.settings')
      ->willReturn($settings);

    $this->messenger->expects($this->once())
      ->method('addStatus')
      ->with('The charts configuration were successfully reset to default.');

    $this->form->submitReset($form, $form_state);
  }

  /**
   * Tests the submitReset() method.
   *
   * @covers ::submitReset
   */
  public function testSubmitResetConfigMissing(): void {
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $this->fileSystem->expects($this->once())
      ->method('realpath')
      ->willReturn(FALSE);
    $this->messenger->expects($this->once())
      ->method('addWarning')
      ->with('We could not reset the configuration to default because the default settings file does not exist. Please re-download the charts module files.');

    $this->form->submitReset($form, $form_state);
  }

}
