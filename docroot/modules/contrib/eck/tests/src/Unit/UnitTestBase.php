<?php

namespace Drupal\Tests\eck\Unit {

  use Drupal\Core\Entity\EntityInterface;
  use Drupal\Core\Entity\EntityManagerInterface;
  use Drupal\Core\Entity\EntityTypeInterface;
  use Drupal\Core\Entity\EntityTypeManagerInterface;
  use Drupal\Core\Entity\EntityTypeRepositoryInterface;
  use Drupal\Tests\UnitTestCase;
  use Drupal\Tests\eck\Unit\TestDoubles\FieldTypePluginManagerMock;
  use Drupal\eck\Entity\EckEntityType;

  /**
   * Base class for unit tests.
   */
  abstract class UnitTestBase extends UnitTestCase {

    /**
     * The entities.
     *
     * @var array
     */
    protected $entities;

    /**
     * The services.
     *
     * @var array
     */
    private $services;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void {
      parent::setUp();
      $this->entities = [];
      $this->prepareContainer();
      $this->registerServiceWithContainerMock('current_user', $this->getNewUserMock());
      $this->registerServiceWithContainerMock('entity_type.manager', $this->getEntityTypeManagerMock());
      $this->registerServiceWithContainerMock('entity_type.repository', $this->getEntityTypeRepositoryMock());
      $this->registerServiceWithContainerMock('plugin.manager.field.field_type', new FieldTypePluginManagerMock());
    }

    /**
     * Prepares a mocked service container.
     */
    private function prepareContainer() {
      $container_class = 'Drupal\Core\DependencyInjection\Container';
      $container = $this->createMock($container_class);
      \Drupal::setContainer($container);

      $container->method('get')->willReturnCallback([
        $this,
        'containerMockGetServiceCallback',
      ]);
    }

    /**
     * Retrieves the entity storage mock.
     */
    private function getEntityStorageMock() {
      $entity_storage = $this->createMock('\Drupal\Core\Entity\EntityStorageInterface');
      $entity_storage->method('loadMultiple')->willReturnCallback([
        $this,
        'entityStorageLoadMultiple',
      ]);
      $entity_storage->method('load')->willReturnCallback([
        $this,
        'entityStorageLoadMultiple',
      ]);

      return $entity_storage;
    }

    /**
     * Retrieves the entity type manager mock.
     */
    private function getEntityTypeManagerMock() {
      $entity_storage = $this->getEntityStorageMock();
      $definition = $this->createMock(EntityTypeInterface::class);

      $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
      $entity_type_manager->method('getStorage')->willReturn($entity_storage);
      $entity_type_manager->method('getDefinition')->willReturn($definition);

      return $entity_type_manager;
    }

    /**
     * Retrieves the entity type repository mock.
     */
    private function getEntityTypeRepositoryMock() {
      $entity_type_repository = $this->createMock(EntityTypeRepositoryInterface::class);
      $entity_type_repository->method('getEntityTypeFromClass')
        ->willReturn('eck_entity_type');
      return $entity_type_repository;
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
     * Creates and returns a mocked user.
     *
     * @return \Drupal\Core\Session\AccountProxyInterface
     *   The mocked user.
     */
    private function getNewUserMock() {
      $user_mock = $this->createMock('\Drupal\Core\Session\AccountProxyInterface');
      $user_mock->method('id')->willReturn(1);
      return $user_mock;
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

    /**
     * Creates the language manager mock.
     */
    protected function createLanguageManagerMock() {
      $current_language_mock = $this->createMock('\Drupal\Core\Language\LanguageInterface');
      $current_language_mock->method('getId')->willReturn('en');

      $mock = $this->createMock('\Drupal\Core\Language\LanguageManagerInterface');
      $mock->method('getCurrentLanguage')->willReturn($current_language_mock);

      return $mock;
    }

    /**
     * Callback for entity storage load multiple.
     */
    public function entityStorageLoadMultiple($id = '') {
      if (!empty($id)) {
        return $this->entities[$id];
      }
      else {
        return $this->entities;
      }
    }

    /**
     * Adds an entity to the mock storage.
     */
    protected function addEntityToStorage(EntityInterface $entity) {
      $this->entities[$entity->id()] = $entity;
    }

    /**
     * Creates a test entity type.
     *
     * @param string $entity_type_id
     *   The entity type id.
     * @param array $values
     *   The values to be set on the created entity.
     *
     * @return \Drupal\eck\Entity\EckEntityType
     *   The created eck entity type.
     */
    protected function createEckEntityType($entity_type_id, array $values = []) {
      $values = $values + [
        'label' => ucfirst($entity_type_id),
        'id' => $entity_type_id,
      ];
      return new EckEntityType($values, $entity_type_id);
    }

    /**
     * Asserts that the array keys of an array equal the expected keys.
     */
    protected function assertArrayKeysEqual($expectedKeys, $arrayToAssert) {
      $this->assertEquals($expectedKeys, array_keys($arrayToAssert));
    }

  }
}

namespace Drupal\eck\Entity {

  if (!\function_exists('t')) {

    /**
     * Mock for the t() function.
     */
    function t($string) {
      return $string;
    }

  }
}
