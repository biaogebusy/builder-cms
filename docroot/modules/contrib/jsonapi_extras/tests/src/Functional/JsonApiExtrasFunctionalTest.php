<?php

namespace Drupal\Tests\jsonapi_extras\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\jsonapi\Functional\JsonApiFunctionalTestBase;
use Drupal\user\Entity\User;
use Symfony\Component\Routing\Route;

/**
 * The test class for the main functionality.
 *
 * @group jsonapi_extras
 */
class JsonApiExtrasFunctionalTest extends JsonApiFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi_extras',
    'basic_auth',
    'jsonapi_test_resource_type_building',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Check that the e0ipso/shaper library is available.
    if (!class_exists("\\Shaper\\DataAdaptor\\DataAdaptorBase")) {
      $this->fail('The e0ipso/shaper library is missing. You can install it with `composer require e0ipso/shaper`.');
    }

    parent::setUp();

    // Add vocabs field to the tags.
    $this->createEntityReferenceField(
      'taxonomy_term',
      'tags',
      'vocabs',
      'Vocabularies',
      'taxonomy_vocabulary',
      'default',
      [
        'target_bundles' => [
          'tags' => 'taxonomy_vocabulary',
        ],
        'auto_create' => TRUE,
      ],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );

    FieldStorageConfig::create([
      'field_name' => 'field_timestamp',
      'entity_type' => 'node',
      'type' => 'timestamp',
      'settings' => [],
      'cardinality' => 1,
    ])->save();

    $field_config = FieldConfig::create([
      'field_name' => 'field_timestamp',
      'label' => 'Timestamp',
      'entity_type' => 'node',
      'bundle' => 'article',
      'required' => FALSE,
      'settings' => [],
      'description' => '',
    ]);
    $field_config->save();

    FieldStorageConfig::create([
      'field_name' => 'field_text_moved',
      'entity_type' => 'node',
      'type' => 'text',
      'settings' => [],
      'cardinality' => 1,
    ])->save();

    $field_config = FieldConfig::create([
      'field_name' => 'field_text_moved',
      'label' => 'Text field',
      'entity_type' => 'node',
      'bundle' => 'article',
      'required' => FALSE,
      'settings' => [],
      'description' => '',
    ]);
    $field_config->save();

    FieldStorageConfig::create([
      'field_name' => 'field_text_moved_new',
      'entity_type' => 'node',
      'type' => 'text',
      'settings' => [],
      'cardinality' => 1,
    ])->save();

    $field_config = FieldConfig::create([
      'field_name' => 'field_text_moved_new',
      'label' => 'Text field new',
      'entity_type' => 'node',
      'bundle' => 'article',
      'required' => FALSE,
      'settings' => [],
      'description' => '',
    ]);
    $field_config->save();

    $config = \Drupal::configFactory()->getEditable('jsonapi_extras.settings');
    $config->set('path_prefix', 'api');
    $config->set('include_count', TRUE);
    $config->save(TRUE);
    static::overrideResources();
    $this->resetAll();
    $role = $this->user->get('roles')[0]->entity;
    $this->grantPermissions(
        $role,
        ['administer nodes', 'administer site configuration']
    );
  }

  /**
   * {@inheritdoc}
   *
   * Appends the 'application/vnd.api+json' if there's no Accept header.
   */
  protected function drupalGet($path, array $options = [], array $headers = []) {
    if (empty($headers['Accept']) && empty($headers['accept'])) {
      $headers['Accept'] = 'application/vnd.api+json';
    }
    return parent::drupalGet($path, $options, $headers);
  }

  /**
   * Test overwriting a field with another field.
   */
  public function testOverwriteFieldWithOtherField() {
    $this->createDefaultContent(1, 1, FALSE, TRUE, static::IS_NOT_MULTILINGUAL);

    // 1. Test if moving a field over another doesn't break
    $this->nodes[0]->field_text_moved->setValue('field_text_moved_value');
    $this->nodes[0]->field_text_moved_new->setValue('field_text_moved_new_value');
    $this->nodes[0]->save();

    $stringResponse = $this->drupalGet('/api/articles');
    $output = Json::decode($stringResponse);

    $this->assertEquals($this->nodes[0]->field_text_moved_new->value, $output['data'][0]['attributes']['field_text_moved']['value']);
  }

  /**
   * Test sorting on an overwritten field.
   */
  public function testSortOverwrittenField() {
    $this->createDefaultContent(2, 1, FALSE, TRUE, static::IS_NOT_MULTILINGUAL);

    $this->nodes[0]->field_text_moved->setValue('c');
    $this->nodes[0]->field_text_moved_new->setValue('b');
    $this->nodes[0]->save();

    $this->nodes[1]->field_text_moved->setValue('d');
    $this->nodes[1]->field_text_moved_new->setValue('a');
    $this->nodes[1]->save();

    $stringResponse = $this->drupalGet('/api/articles', ['query' => ['sort' => 'field_text_moved.value']]);
    $output = Json::decode($stringResponse);

    // Check if order changed as expected.
    $this->assertEquals('a', $output['data'][0]['attributes']['field_text_moved']['value']);
    $this->assertEquals('b', $output['data'][1]['attributes']['field_text_moved']['value']);

    $stringResponse = $this->drupalGet('/api/articles', ['query' => ['sort' => '-field_text_moved.value']]);
    $output = Json::decode($stringResponse);

    // Check if order changed as expected.
    $this->assertEquals('b', $output['data'][0]['attributes']['field_text_moved']['value']);
    $this->assertEquals('a', $output['data'][1]['attributes']['field_text_moved']['value']);

  }

  /**
   * Tests that resource type fields can be aliased per resource type.
   *
   * @see Drupal\jsonapi_test_resource_type_building\EventSubscriber\ResourceTypeBuildEventSubscriber
   *
   * @todo Create a test similar to this
   */
  public function testResourceTypeFieldAliasing() {
    /** @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceTypeRepository $resourceTypeRepository */
    $resourceTypeRepository = $this->container->get('jsonapi.resource_type.repository');

    $nodeArticleType = $resourceTypeRepository->getByTypeName('node--article');
    $this->assertSame('owner', $nodeArticleType->getPublicName('uid'));

    $resource_type_field_aliases = [
      'node--article' => [
        'uid' => 'author',
      ],
    ];
    \Drupal::state()->set('jsonapi_test_resource_type_builder.resource_type_field_aliases', $resource_type_field_aliases);
    Cache::invalidateTags(['jsonapi_resource_types']);

    $this->assertSame('author', $resourceTypeRepository->getByTypeName('node--article')->getPublicName('uid'));
  }

  /**
   * Test the GET method.
   */
  public function testRead() {

    $num_articles = 61;
    $this->createDefaultContent($num_articles, 5, TRUE, TRUE, static::IS_NOT_MULTILINGUAL);
    // Make the link for node/3 to point to an entity.
    $this->nodes[3]->field_link->setValue(['uri' => 'entity:node/' . $this->nodes[2]->id()]);
    $this->nodes[3]->save();
    $this->nodes[40]->uid->set(0, 1);
    $this->nodes[40]->save();

    // 1. Make sure the api root is under '/api' and not '/jsonapi'.
    /** @var \Symfony\Component\Routing\RouteCollection $route_collection */
    $route_collection = \Drupal::service('router.route_provider')
      ->getRoutesByPattern('/api');
    $this->assertInstanceOf(
      Route::class, $route_collection->get('jsonapi.resource_list')
    );
    $this->drupalGet('/jsonapi');
    $this->assertSession()->statusCodeEquals(404);

    // 2. Make sure the count is included in collections. This also tests the
    // overridden paths.
    $output = Json::decode($this->drupalGet('/api/articles'));
    $this->assertSame($num_articles, (int) $output['meta']['count']);
    $this->assertSession()->statusCodeEquals(200);

    // 3. Check disabled resources.
    $this->drupalGet('/api/taxonomy_vocabulary/taxonomy_vocabulary');
    $this->assertSession()->statusCodeEquals(404);

    // 4. Check renamed fields.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[0]->uuid()));
    $this->assertArrayNotHasKey('type', $output['data']['attributes']);
    $this->assertArrayHasKey('contentType', $output['data']['relationships']);
    $this->assertSame('contentTypes', $output['data']['relationships']['contentType']['data']['type']);
    $output = Json::decode($this->drupalGet('/api/contentTypes/' . $this->nodes[0]->type->entity->uuid()));
    $this->assertArrayNotHasKey('type', $output['data']['attributes']);
    $this->assertSame('article', $output['data']['attributes']['machineName']);

    // 5. Check disabled fields.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[1]->uuid()));
    $this->assertArrayNotHasKey('uuid', $output['data']['attributes']);

    // 6. Test the field enhancers: DateTimeEnhancer.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[2]->uuid()));
    $timestamp = \DateTime::createFromFormat('Y-m-d\TH:i:sO', $output['data']['attributes']['createdAt'])
      ->format('U');
    $this->assertSame((int) $timestamp, $this->nodes[2]->getCreatedTime());

    // 7. Test the field enhancers: UuidLinkEnhancer.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[3]->uuid()));
    $expected_link = 'entity:node/article/' . $this->nodes[2]->uuid();
    $this->assertSame($expected_link, $output['data']['attributes']['link']['uri']);

    // 8. Test the field enhancers: SingleNestedEnhancer.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[3]->uuid()));
    $this->assertIsString($output['data']['attributes']['body']);

    // 9. Test the related endpoint.
    // This tests the overridden resource name, the overridden field names and
    // the disabled fields.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[4]->uuid() . '/contentType'));
    $this->assertArrayNotHasKey('type', $output['data']['attributes']);
    $this->assertSame('article', $output['data']['attributes']['machineName']);
    $this->assertSame('contentTypes', $output['data']['type']);
    $this->assertArrayNotHasKey('uuid', $output['data']['attributes']);

    // 10. Test the relationships endpoint.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[4]->uuid() . '/relationships/contentType'));
    $this->assertSame('contentTypes', $output['data']['type']);
    $this->assertArrayHasKey('id', $output['data']);

    // 11. Test the related endpoint on a multiple cardinality relationship.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[5]->uuid() . '/tags'));
    $this->assertCount(count($this->nodes[5]->get('field_tags')->getValue()), $output['data']);
    $this->assertSame('taxonomy_term--tags', $output['data'][0]['type']);

    // 12. Test the relationships endpoint.
    $output = Json::decode($this->drupalGet('/api/articles/' . $this->nodes[5]->uuid() . '/relationships/tags'));
    $this->assertCount(count($this->nodes[5]->get('field_tags')->getValue()), $output['data']);
    $this->assertArrayHasKey('id', $output['data'][0]);

    // 13. Test a disabled related resource of single cardinality.
    $this->drupalGet('/api/taxonomy_term/tags/' . $this->tags[0]->uuid() . '/vid');
    $this->assertSession()->statusCodeEquals(404);
    $output = Json::decode($this->drupalGet('/api/taxonomy_term/tags/' . $this->tags[0]->uuid() . '/relationships/vid'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame(NULL, $output['data']);

    // 14. Test a disabled related resource of multiple cardinality.
    $this->tags[1]->vocabs->set(0, 'tags');
    $this->tags[1]->save();
    $output = Json::decode($this->drupalGet('/api/taxonomy_term/tags/' . $this->tags[0]->uuid() . '/vocabs'));
    $this->assertTrue(empty($output['data']));
    $output = Json::decode($this->drupalGet('/api/taxonomy_term/tags/' . $this->tags[0]->uuid() . '/relationships/vocabs'));
    $this->assertTrue(empty($output['data']));

    // 15. Test included resource.
    $output = Json::decode($this->drupalGet(
      '/api/articles/' . $this->nodes[6]->uuid(),
      ['query' => ['include' => 'owner']]
    ));
    $this->assertSame('user--user', $output['included'][0]['type']);

    // 16. Test disabled included resources.
    $output = Json::decode($this->drupalGet(
      '/api/taxonomy_term/tags/' . $this->tags[0]->uuid(),
      ['query' => ['include' => 'vocabs,vid']]
    ));
    $this->assertArrayNotHasKey('included', $output);

    // 17. Test nested filters with renamed field.
    $output = Json::decode($this->drupalGet(
      '/api/articles',
      [
        'query' => [
          'filter' => [
            'owner.name' => [
              'value' => User::load(1)->getAccountName(),
            ],
          ],
        ],
      ]
    ));
    // There is only one article for the admin.
    $this->assertSame($this->nodes[40]->uuid(), $output['data'][0]['id']);
  }

  /**
   * Test POST/PATCH.
   */
  public function testWrite() {
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save(TRUE);

    $this->createDefaultContent(0, 3, FALSE, FALSE, static::IS_NOT_MULTILINGUAL);
    // 1. Successful post.
    $collection_url = Url::fromRoute('jsonapi.articles.collection');
    $body = [
      'data' => [
        'type' => 'articles',
        'attributes' => [
          'langcode' => 'en',
          'title' => 'My custom title',
          'default_langcode' => '1',
          'body' => 'Custom value',
          'timestamp' => '2017-12-23T08:45:17+0100',
        ],
        'relationships' => [
          'contentType' => [
            'data' => [
              'type' => 'contentTypes',
              'id' => NodeType::load('article')->uuid(),
            ],
          ],
          'owner' => [
            'data' => ['type' => 'user--user', 'id' => User::load(1)->uuid()],
          ],
          'tags' => [
            'data' => [
              ['type' => 'taxonomy_term--tags', 'id' => $this->tags[0]->uuid()],
              ['type' => 'taxonomy_term--tags', 'id' => $this->tags[1]->uuid()],
            ],
          ],
        ],
      ],
    ];
    $response = $this->request('POST', $collection_url, [
      'body' => Json::encode($body),
      'auth' => [$this->user->getAccountName(), $this->user->pass_raw],
      'headers' => [
        'Content-Type' => 'application/vnd.api+json',
        'Accept' => 'application/vnd.api+json',
      ],
    ]);
    $created_response = Json::decode((string) $response->getBody());
    $this->assertEquals(201, $response->getStatusCode());
    $this->assertArrayHasKey('internalId', $created_response['data']['attributes']);
    $this->assertCount(2, $created_response['data']['relationships']['tags']['data']);
    $this->assertSame($created_response['data']['links']['self']['href'], $response->getHeader('Location')[0]);
    $date = new \DateTime($body['data']['attributes']['timestamp']);
    $created_node = Node::load($created_response['data']['attributes']['internalId']);
    $this->assertSame((int) $date->format('U'), (int) $created_node->get('field_timestamp')->value);

    // 2. Successful relationships PATCH.
    $uuid = $created_response['data']['id'];
    $relationships_url = Url::fromUserInput('/api/articles/' . $uuid . '/relationships/tags');
    $body = [
      'data' => [
        ['type' => 'taxonomy_term--tags', 'id' => $this->tags[2]->uuid()],
      ],
    ];
    $response = $this->request('POST', $relationships_url, [
      'body' => Json::encode($body),
      'auth' => [$this->user->getAccountName(), $this->user->pass_raw],
      'headers' => ['Content-Type' => 'application/vnd.api+json'],
    ]);
    $created_response = Json::decode((string) $response->getBody());
    $this->assertCount(3, $created_response['data']);
  }

  /**
   * Creates the JSON:API Resource Config entities to override the resources.
   */
  protected static function overrideResources() {
    // Disable the taxonomy_vocabulary resource.
    JsonapiResourceConfig::create([
      'id' => 'taxonomy_vocabulary--taxonomy_vocabulary',
      'disabled' => TRUE,
      'path' => 'taxonomy_vocabulary/taxonomy_vocabulary',
      'resourceType' => 'taxonomy_vocabulary--taxonomy_vocabulary',
      'resourceFields' => [],
    ])->save();
    // Override paths and fields in the articles resource.
    JsonapiResourceConfig::create([
      'id' => 'node--article',
      'disabled' => FALSE,
      'path' => 'articles',
      'resourceType' => 'articles',
      'resourceFields' => [
        'field_text_moved' => [
          'fieldName' => 'field_text_moved',
          'publicName' => 'field_text_moved',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'field_text_moved_new' => [
          'fieldName' => 'field_text_moved_new',
          'publicName' => 'field_text_moved',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'field_date_sort' => [
          'fieldName' => 'field_date_sort',
          'publicName' => 'field_date_sort',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'field_date_sort_new' => [
          'fieldName' => 'field_date_sort_new',
          'publicName' => 'field_date_sort',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'nid' => [
          'fieldName' => 'nid',
          'publicName' => 'internalId',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'uuid' => [
          'fieldName' => 'uuid',
          'publicName' => 'uuid',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'vid' => [
          'fieldName' => 'vid',
          'publicName' => 'vid',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'langcode' => [
          'fieldName' => 'langcode',
          'publicName' => 'langcode',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'type' => [
          'fieldName' => 'type',
          'publicName' => 'contentType',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'status' => [
          'fieldName' => 'status',
          'publicName' => 'isPublished',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'title' => [
          'fieldName' => 'title',
          'publicName' => 'title',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'uid' => [
          'fieldName' => 'uid',
          'publicName' => 'owner',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'created' => [
          'fieldName' => 'created',
          'publicName' => 'createdAt',
          'enhancer' => [
            'id' => 'date_time',
            'settings' => ['dateTimeFormat' => 'Y-m-d\TH:i:sO'],
          ],
          'disabled' => FALSE,
        ],
        'changed' => [
          'fieldName' => 'changed',
          'publicName' => 'updatedAt',
          'enhancer' => [
            'id' => 'date_time',
            'settings' => ['dateTimeFormat' => 'Y-m-d\TH:i:sO'],
          ],
          'disabled' => FALSE,
        ],
        'promote' => [
          'fieldName' => 'promote',
          'publicName' => 'isPromoted',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'sticky' => [
          'fieldName' => 'sticky',
          'publicName' => 'sticky',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'revision_timestamp' => [
          'fieldName' => 'revision_timestamp',
          'publicName' => 'revision_timestamp',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'revision_uid' => [
          'fieldName' => 'revision_uid',
          'publicName' => 'revision_uid',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'revision_log' => [
          'fieldName' => 'revision_log',
          'publicName' => 'revision_log',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'revision_translation_affected' => [
          'fieldName' => 'revision_translation_affected',
          'publicName' => 'revision_translation_affected',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'default_langcode' => [
          'fieldName' => 'default_langcode',
          'publicName' => 'default_langcode',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'path' => [
          'fieldName' => 'path',
          'publicName' => 'path',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'body' => [
          'fieldName' => 'body',
          'publicName' => 'body',
          'enhancer' => ['id' => 'nested', 'settings' => ['path' => 'value']],
          'disabled' => FALSE,
        ],
        'field_link' => [
          'fieldName' => 'field_link',
          'publicName' => 'link',
          'enhancer' => ['id' => 'uuid_link'],
          'disabled' => FALSE,
        ],
        'field_timestamp' => [
          'fieldName' => 'field_timestamp',
          'publicName' => 'timestamp',
          'enhancer' => [
            'id' => 'date_time',
            'settings' => ['dateTimeFormat' => 'Y-m-d\TH:i:sO'],
          ],
          'disabled' => FALSE,
        ],
        'comment' => [
          'fieldName' => 'comment',
          'publicName' => 'comment',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'field_image' => [
          'fieldName' => 'field_image',
          'publicName' => 'image',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'field_recipes' => [
          'fieldName' => 'field_recipes',
          'publicName' => 'recipes',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'field_tags' => [
          'fieldName' => 'field_tags',
          'publicName' => 'tags',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
      ],
    ])->save();
    // Override the resource type in the node_type resource.
    JsonapiResourceConfig::create([
      'id' => 'node_type--node_type',
      'disabled' => FALSE,
      'path' => 'contentTypes',
      'resourceType' => 'contentTypes',
      'resourceFields' => [
        'type' => [
          'fieldName' => 'type',
          'publicName' => 'machineName',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'status' => [
          'fieldName' => 'status',
          'publicName' => 'isEnabled',
          'enhancer' => ['id' => ''],
          'disabled' => FALSE,
        ],
        'langcode' => [
          'fieldName' => 'langcode',
          'publicName' => 'langcode',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
        'uuid' => [
          'fieldName' => 'uuid',
          'publicName' => 'uuid',
          'enhancer' => ['id' => ''],
          'disabled' => TRUE,
        ],
      ],
    ])->save();

  }

  /**
   * Test disabled resource show in admin list.
   */
  public function testDisabledResourcesShowInAdminList() {
    $vocabulary = Vocabulary::create([
      'name' => $this->randomMachineName(),
      'vid' => mb_strtolower($this->randomMachineName()),
    ]);
    $vocabulary->save();
    $admin_user = $this->createUser([], 'test_admin_user', TRUE);
    $this->drupalLogin($admin_user);

    $this->drupalGet('/admin/config/services/jsonapi/resource_types');

    $row = $this->assertSession()->elementExists('css', sprintf('#jsonapi-enabled-resources-list table tr:contains("%s")', 'taxonomy_term--' . $vocabulary->id()));
    $this->assertSession()->elementExists('named', ['link', 'Overwrite'], $row);
    $this->drupalGet('/admin/config/services/jsonapi/add/resource_types/taxonomy_term/' . $vocabulary->id());
    $this->getSession()->getPage()->checkField('edit-disabled');
    $this->getSession()->getPage()->pressButton('edit-submit');
    $row = $this->assertSession()->elementExists('css', sprintf('#jsonapi-disabled-resources-list table tr:contains("%s")', 'taxonomy_term--' . $vocabulary->id()));
    $this->assertSession()->elementExists('named', ['link', 'Revert'], $row);

    // Add another vocabulary.
    $vocabulary2 = Vocabulary::create([
      'name' => $this->randomMachineName(),
      'vid' => mb_strtolower($this->randomMachineName()),
    ]);
    $vocabulary2->save();
    // Set the default to disabled.
    \Drupal::configFactory()->getEditable('jsonapi_extras.settings')->set('default_disabled', TRUE)->save();
    Cache::invalidateTags(['jsonapi_resource_types']);
    $this->drupalGet('/admin/config/services/jsonapi/resource_types');
    $row = $this->assertSession()->elementExists('css', sprintf('#jsonapi-disabled-resources-list table tr:contains("%s")', 'taxonomy_term--' . $vocabulary2->id()));
    $this->assertSession()->elementExists('named', ['link', 'Enable'], $row);

    // Test that internal entity-types can't be enabled.
    \Drupal::service('module_installer')->install(['entity_test']);
    $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo('entity_test_no_label');
    $this->drupalGet('/admin/config/services/jsonapi/resource_types');
    foreach (array_keys($bundle_info) as $bundle) {
      $this->assertSession()->elementNotContains('css', '#jsonapi-disabled-resources-list', 'entity_test_no_label--' . $bundle);
    }
  }

}
