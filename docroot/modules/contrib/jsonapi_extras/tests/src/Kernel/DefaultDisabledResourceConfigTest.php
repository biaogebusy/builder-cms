<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi_extras\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceTypeRepository;
use Drupal\KernelTests\KernelTestBase;

/**
 * Defines a test for the 'default_disabled' setting on resource configs.
 *
 * @group jsonapi_extras
 */
class DefaultDisabledResourceConfigTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi',
    'jsonapi_extras',
    'entity_test',
    'serialization',
    'user',
    'system',
    'text',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
  }

  /**
   * Tests the default disabled setting.
   */
  public function testDefaultDisabled(): void {
    $resource_respository = $this->container->get('jsonapi.resource_type.repository');
    assert($resource_respository instanceof ConfigurableResourceTypeRepository);
    $resource = $resource_respository->get('entity_test', 'entity_test');
    $this->assertFalse($resource->isInternal());
    $this->config('jsonapi_extras.settings')->set('default_disabled', TRUE)->save();
    $resource_respository->reset();
    Cache::invalidateTags(['jsonapi_resource_types']);
    $resource = $resource_respository->get('entity_test', 'entity_test');
    $this->assertTrue($resource->isInternal());
    JsonapiResourceConfig::create([
      'id' => 'entity_test--entity_test',
      'disabled' => FALSE,
      'path' => 'entity_test/entity_test',
      'resourceType' => 'entity_test--entity_test',
      'resourceFields' => [],
    ])->save();
    Cache::invalidateTags(['jsonapi_resource_types']);
    $resource = $resource_respository->get('entity_test', 'entity_test');
    $this->assertFalse($resource->isInternal());
  }

}
