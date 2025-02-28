<?php

namespace Drupal\Tests\select_or_other\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Base class for unit tests.
 */
abstract class UnitTestBase extends UnitTestCase {

  /**
   * The mock builder.
   *
   * @var \PHPUnit\Framework\MockObject\MockBuilder
   */
  protected $mockBuilder;

  /**
   * The container mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  private $containerMock;

  /**
   * The services.
   *
   * @var array
   */
  private $services;

  /**
   * Retrieves the tested class name.
   *
   * @return string
   *   The fully qualified class name of the subject under test.
   */
  abstract protected function getTestedClassName();

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->prepareContainer();
    $this->addMockServicesToContainer();
    $this->mockBuilder = $this->getMockBuilder($this->getTestedClassName());
  }

  /**
   * Prepares a mocked service container.
   */
  private function prepareContainer() {
    $container_class = 'Drupal\Core\DependencyInjection\Container';
    $methods = get_class_methods($container_class);
    /** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
    $this->containerMock = $container = $this->getMockBuilder($container_class)
      ->disableOriginalConstructor()
      ->onlyMethods($methods)
      ->getMock();
    \Drupal::setContainer($container);

    $this->containerMock->method('get')->willReturnCallback([
      $this,
      'containerMockGetServiceCallback',
    ]);
  }

  /**
   * Adds mocked services to the container.
   */
  private function addMockServicesToContainer() {
    $this->registerServiceWithContainerMock('current_user', $this->getNewUserMock());
    $this->registerServiceWithContainerMock('entity_type.manager', $this->getNewEntityTypeManagerMock());
    $this->registerServiceWithContainerMock('string_translation', $this->getStringTranslationStub());
  }

  /**
   * Creates and returns a mocked user.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The mocked user.
   */
  private function getNewUserMock() {
    $user_mock = $this->getMockForAbstractClass('\Drupal\Core\Session\AccountProxyInterface');
    $user_mock->method('id')->willReturn(1);
    return $user_mock;
  }

  /**
   * Creates a new entity type manager mock.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The new Entity Type Manager Mock.
   */
  private function getNewEntityTypeManagerMock() {
    $field_storage_config = $this->getMockForAbstractClass('\Drupal\field\FieldStorageConfigInterface');
    $field_storage_config->method('setSetting')->willReturnSelf();
    $entity_storage_methods = [
      'load' => $field_storage_config,
    ];
    $entity_type_manager_methods = [
      'getStorage' => $this->getMockForAbstractClassWithMethods('\Drupal\Core\Entity\EntityStorageInterface', $entity_storage_methods),
    ];

    return $this->getMockForAbstractClassWithMethods('\Drupal\Core\Entity\EntityTypeManagerInterface', $entity_type_manager_methods);
  }

  /**
   * Creates and returns two basic mocks.
   *
   * One WidgetBase and one for the tested class.
   *
   * @return array
   *   The two mocks.
   */
  protected function getBasicMocks() {
    $methods = ['getSetting', 'getSelectedOptions', 'getColumn'];
    $parent = $this->getMockBuilder('Drupal\select_or_other\Plugin\Field\FieldWidget\WidgetBase')
      ->onlyMethods($methods)
      ->disableOriginalConstructor()
      ->getMockForAbstractClass();
    $mock = $this->getMockBuilder($this->getTestedClassName())
      ->onlyMethods($methods)
      ->disableOriginalConstructor()
      ->getMock();
    $mocks = [$parent, $mock];

    $reflected_field_definition = new \ReflectionProperty('Drupal\select_or_other\Plugin\Field\FieldWidget\WidgetBase', 'fieldDefinition');
    $reflected_field_definition->setAccessible(TRUE);
    /** @var \PHPUnit\Framework\MockObject\MockObject $current_mock */
    foreach ($mocks as $current_mock) {
      $field_definition_methods = ['getFieldStorageDefinition' => $this->getMockForAbstractClass('Drupal\Core\Field\FieldStorageDefinitionInterface')];
      $field_definition = $this->getMockForAbstractClassWithMethods('\Drupal\Core\Field\FieldDefinitionInterface', $field_definition_methods);
      $reflected_field_definition->setValue($current_mock, $field_definition);

      $current_mock->method('getSelectedOptions')->willReturn([]);
      $current_mock->method('getColumn')->willReturn('');
    }

    return $mocks;
  }

  /**
   * Creates a mock for an abstract class with some mapped methods.
   *
   * @param string $abstractClassName
   *   The class name.
   * @param array $methods
   *   The methods to mock.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The created mock.
   */
  protected function getMockForAbstractClassWithMethods($abstractClassName, array $methods) {
    $mock = $this->getMockForAbstractClass($abstractClassName);

    foreach ($methods as $method => $return_value) {
      $mock->method($method)->willReturn($return_value);
    }

    return $mock;
  }

  /**
   * Registers a (mocked) service with the mocked service container.
   *
   * @param string $service_id
   *   The service id.
   * @param mixed $service
   *   The service to be returned when the service_id is requested from the
   *   container.
   */
  protected function registerServiceWithContainerMock($service_id, $service) {
    $this->services[$service_id] = $service;
  }

  /**
   * Callback for the get method on the mocked service container.
   *
   * @param string $service_id
   *   The service identifier being called.
   *
   * @return mixed
   *   A (mocked) service class if one has been set for the given service id
   *   or NULL.
   */
  public function containerMockGetServiceCallback($service_id) {
    if (isset($this->services[$service_id])) {
      return $this->services[$service_id];
    }
    return NULL;
  }

}
