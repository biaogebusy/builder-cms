<?php

namespace Drupal\Tests\jsonapi_extras\Kernel\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigException;
use Drupal\jsonapi\Access\EntityAccessChecker;
use Drupal\jsonapi\Controller\EntityResource;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel as JsonApiDocumentTopLevel2;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeAttribute;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\jsonapi\Controller\EntityResource
 * @covers \Drupal\jsonapi_extras\Normalizer\ConfigEntityDenormalizer
 * @group jsonapi_extras
 * @group legacy
 *
 * @internal
 */
class EntityResourceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'jsonapi',
    'serialization',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create([
      'type' => 'article',
    ])->save();
    Role::create([
      'id' => RoleInterface::ANONYMOUS_ID,
    ])->save();
  }

  /**
   * @covers ::createIndividual
   */
  public function testCreateIndividualConfig() {
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('administer content types')
      ->save();
    $resource_type = new ResourceType('node_type', 'node_type', NodeType::class);
    $resource_type->setRelatableResourceTypes([]);
    $payload = Json::encode([
      'data' => [
        'type' => 'node--test',
        'attributes' => [
          'type' => 'test',
          'name' => 'Test Type',
          'description' => 'Lorem ipsum',
        ],
      ],
    ]);
    $entity_resource = new EntityResource(
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $this->container->get('jsonapi.resource_type.repository'),
      $this->container->get('renderer'),
      $this->container->get('entity.repository'),
      $this->container->get('jsonapi.include_resolver'),
      new EntityAccessChecker(
        $this->container->get('jsonapi.resource_type.repository'),
        $this->container->get('router.no_access_checks'),
        $this->container->get('current_user'),
        $this->container->get('entity.repository')
      ),
      $this->container->get('jsonapi.field_resolver'),
      $this->container->get('jsonapi.serializer'),
      $this->container->get('datetime.time'),
      $this->container->get('current_user')
    );
    $response = $entity_resource->createIndividual($resource_type, Request::create('/jsonapi/node_type/node_type', 'POST', [], [], [], [], $payload));
    // As a side effect, the node type will also be saved.
    $node_type = NodeType::load('test');
    $this->assertInstanceOf(JsonApiDocumentTopLevel2::class, $response->getResponseData());
    $data = $response->getResponseData()->getData()->getIterator()->offsetGet(0);
    $this->assertInstanceOf(ResourceObject::class, $data);
    $this->assertEquals($node_type->uuid(), $data->getId());
    $this->assertEquals(201, $response->getStatusCode());
  }

  /**
   * @covers ::patchIndividual
   * @dataProvider patchIndividualConfigProvider
   */
  public function testPatchIndividualConfig($values) {
    // List of fields to be ignored.
    $ignored_fields = ['uuid', 'entityTypeId', 'type'];
    $node_type = NodeType::create([
      'type' => 'test',
      'name' => 'Test Type',
      'description' => '',
    ]);
    $node_type->save();

    $parsed_node_type = NodeType::create($values);
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('administer content types')
      ->save();
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('edit any article content')
      ->save();
    $payload = Json::encode([
      'data' => [
        'type' => 'node_type--node_type',
        'id' => $node_type->uuid(),
        'attributes' => $values,
      ],
    ]);
    $request = Request::create('/jsonapi/node/node_type/' . $node_type->uuid(), 'PATCH', [], [], [], [], $payload);

    $resource_type = new ResourceType('node_type', 'node_type', NodeType::class, FALSE, TRUE, TRUE, FALSE, [
      'type' => new ResourceTypeAttribute('drupal_internal__type'),
    ]);
    $resource_type->setRelatableResourceTypes([]);
    $entity_resource = new EntityResource(
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $this->container->get('jsonapi.resource_type.repository'),
      $this->container->get('renderer'),
      $this->container->get('entity.repository'),
      $this->container->get('jsonapi.include_resolver'),
      new EntityAccessChecker(
        $this->container->get('jsonapi.resource_type.repository'),
        $this->container->get('router.no_access_checks'),
        $this->container->get('current_user'),
        $this->container->get('entity.repository')
      ),
      $this->container->get('jsonapi.field_resolver'),
      $this->container->get('jsonapi.serializer'),
      $this->container->get('datetime.time'),
      $this->container->get('current_user')
    );
    $response = $entity_resource->patchIndividual($resource_type, $node_type, $request);

    // As a side effect, the node will also be saved.
    $this->assertInstanceOf(JsonApiDocumentTopLevel2::class, $response->getResponseData());
    $updated_node_type = $response->getResponseData()->getData()->getIterator()->offsetGet(0);
    $this->assertInstanceOf(ResourceObject::class, $updated_node_type);
    // If the field is ignored then we should not see a difference.
    foreach ($values as $field_name => $value) {
      in_array($field_name, $ignored_fields) ?
        $this->assertNotSame($value, $node_type->get($field_name)) :
        $this->assertSame($value, $node_type->get($field_name));
    }
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * Provides data for the testPatchIndividualConfig.
   *
   * @return array
   *   The input data for the test function.
   */
  public function patchIndividualConfigProvider() {
    return [
      [['description' => 'PATCHED', 'status' => FALSE]],
      [[]],
    ];
  }

  /**
   * @covers ::patchIndividual
   * @dataProvider patchIndividualConfigFailedProvider
   */
  public function testPatchIndividualFailedConfig($values, $expected_message) {
    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage($expected_message);
    $this->testPatchIndividualConfig($values);
  }

  /**
   * Provides data for the testPatchIndividualFailedConfig.
   *
   * @return array
   *   The input data for the test function.
   */
  public function patchIndividualConfigFailedProvider() {
    return [
      [
        ['type' => 'article', 'status' => FALSE],
        "The machine name of the 'Content type' bundle cannot be changed.",
      ],
    ];
  }

  /**
   * @covers ::deleteIndividual
   */
  public function testDeleteIndividualConfig() {
    $node_type = NodeType::create([
      'type' => 'test',
      'name' => 'Test Type',
      'description' => 'Lorem ipsum',
    ]);
    $id = $node_type->id();
    $node_type->save();
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('administer content types')
      ->save();
    $entity_resource = new EntityResource(
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $this->container->get('jsonapi.resource_type.repository'),
      $this->container->get('renderer'),
      $this->container->get('entity.repository'),
      $this->container->get('jsonapi.include_resolver'),
      new EntityAccessChecker(
        $this->container->get('jsonapi.resource_type.repository'),
        $this->container->get('router.no_access_checks'),
        $this->container->get('current_user'),
        $this->container->get('entity.repository')
      ),
      $this->container->get('jsonapi.field_resolver'),
      $this->container->get('jsonapi.serializer'),
      $this->container->get('datetime.time'),
      $this->container->get('current_user')
    );
    $response = $entity_resource->deleteIndividual($node_type);
    // As a side effect, the node will also be deleted.
    $count = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->getQuery()
      ->condition('type', $id)
      ->count()
      ->execute();
    $this->assertEquals(0, $count);
    $this->assertNull($response->getResponseData());
    $this->assertEquals(204, $response->getStatusCode());
  }

}
