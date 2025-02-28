<?php

namespace Drupal\Tests\state_machine\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\state_machine\Guard\GuardFactoryInterface;
use Drupal\state_machine\Plugin\Workflow\Workflow;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\WorkflowGroupManagerInterface;
use Drupal\state_machine\WorkflowManager;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the cache of the workflow manager.
 *
 * @coversDefaultClass \Drupal\state_machine\WorkflowManager
 * @group state_machine
 */
class WorkflowManagerCacheTest extends UnitTestCase {

  /**
   * An instance of the WorkflowManager. This is the system under test.
   *
   * @var \Drupal\state_machine\WorkflowManager
   */
  protected $workflowManager;

  /**
   * A mocked instance of the dependency injection container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $container;

  /**
   * A mocked instance of the module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $moduleHandler;

  /**
   * A mocked instance of a cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $cacheBackend;

  /**
   * A mocked instance of the workflow group manager.
   *
   * @var \Drupal\state_machine\WorkflowGroupManagerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $workflowGroupManager;

  /**
   * The ID of a mocked workflow plugin used in the test.
   *
   * @var string
   */
  protected $pluginId = 'test_workflow';

  /**
   * The ID of a mocked workflow group used in the test.
   *
   * @var string
   */
  protected $groupId = 'test_group';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container = $this->prophesize(ContainerInterface::class);
    \Drupal::setContainer($this->container->reveal());
    // Mock the dependencies to inject into the workflow manager.
    $this->moduleHandler = $this->prophesize(ModuleHandlerInterface::class);
    $this->cacheBackend = $this->prophesize(CacheBackendInterface::class);
    $this->workflowGroupManager = $this->prophesize(WorkflowGroupManagerInterface::class);

    // Instantiate the workflow manager. This is the system under test.
    $this->workflowManager = new WorkflowManager(
      $this->moduleHandler->reveal(),
      $this->cacheBackend->reveal(),
      $this->workflowGroupManager->reveal()
    );
  }

  /**
   * Tests that workflow plugins are cached upon creation.
   *
   * @covers ::createInstance
   */
  public function testCreateInstance() {
    // In this test we will ask the workflow manager repeatedly for an instance
    // of a single workflow plugin. Since the manager is supposed to cache the
    // plugins, it is expected that the GuardFactory (which is a dependency of
    // the Workflow plugin) is not retrieved more than once from the container.
    $guard_factory = $this->prophesize(GuardFactoryInterface::class);
    $this->container->get('state_machine.guard_factory')
      ->willReturn($guard_factory->reveal())
      ->shouldBeCalledOnce();

    // It is expected that the workflow manager will retrieve the complete list
    // of workflows from the cache backend in order to look up the definition of
    // the workflow plugin that we are creating. Even though we are creating
    // multiple instances, this should only be called once since the result
    // should be cached in memory.
    $this->cacheBackend->get('workflow')
      ->willReturn((object) [
        'data' => $this->getMockWorkflowDefinitions(),
      ])
      ->shouldBeCalledOnce();

    // Once it found the workflow definition, it is expected that the workflow
    // group manager will be asked for the definition of the workflow plugin
    // that is used by the group. The workflow manager requires this data in
    // order to discover the class name of the workflow plugin so that it can be
    // instantiated. This too should be called only once.
    $this->workflowGroupManager->getDefinition($this->groupId)
      ->willReturn([
        'workflow_class' => Workflow::class,
      ])
      ->shouldBeCalledOnce();

    // Request the same plugin instance multiple times from the workflow
    // manager. The first instance is cached, and all subsequent invocations
    // will retrieve the instance from cache.
    for ($i = 0; $i < 5; $i++) {
      $plugin = $this->workflowManager->createInstance($this->pluginId);
      $this->assertInstanceOf(WorkflowInterface::class, $plugin);
    }
  }

  /**
   * Gets a mocked workflow plugin definition.
   *
   * @return array
   *   The mocked workflow definition.
   */
  protected function getMockWorkflowDefinitions() {
    return [
      'test_workflow' => [
        'group' => $this->groupId,
        'states' => [],
        'transitions' => [],
      ],
    ];
  }

}
