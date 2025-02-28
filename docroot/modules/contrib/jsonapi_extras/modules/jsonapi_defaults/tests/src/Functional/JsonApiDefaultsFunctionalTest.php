<?php

namespace Drupal\Tests\jsonapi_defaults\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Drupal\Tests\jsonapi_extras\Functional\JsonApiExtrasFunctionalTestBase;
use GuzzleHttp\Psr7\Query;

/**
 * The test class for the JSON API Defaults functionality.
 *
 * @group jsonapi_extras
 */
class JsonApiDefaultsFunctionalTest extends JsonApiExtrasFunctionalTestBase {

  /**
   * The value for the override of the page limit.
   */
  const PAGE_LIMIT_OVERRIDE_VALUE = 100;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi_defaults',
  ];

  /**
   * Test regression on sorting from issue 3322635.
   */
  public function testSortRegression3322635() {
    $this->setResouceConfigValue([
      'default_filter' => [],
      'default_sorting' => [],
    ]);

    $this->createDefaultContent(2, 5, TRUE, TRUE, static::IS_NOT_MULTILINGUAL);

    $this->nodes[0]->title->setValue('b');
    $this->nodes[0]->save();

    $this->nodes[1]->title->setValue('a');
    $this->nodes[1]->save();

    $stringResponse = $this->drupalGet('/api/articles', ['query' => ['sort' => 'title']]);
    $output = Json::decode($stringResponse);

    // Check if order changed as expected.
    $this->assertEquals('a', $output['data'][0]['attributes']['title']);
    $this->assertEquals('b', $output['data'][1]['attributes']['title']);

    $stringResponse = $this->drupalGet('/api/articles', ['query' => ['sort' => '-title']]);
    $output = Json::decode($stringResponse);

    // Check if order changed as expected.
    $this->assertEquals('b', $output['data'][0]['attributes']['title']);
    $this->assertEquals('a', $output['data'][1]['attributes']['title']);

  }

  /**
   * Test the GET method.
   */
  public function testRead() {
    $this->createDefaultContent(4, 5, TRUE, TRUE, static::IS_NOT_MULTILINGUAL);
    // 1. Apply default filters and includes on a resource and a related
    // resource.
    $response = $this->drupalGet('/api/articles');
    $parsed_response = Json::decode($response);
    $this->assertArrayHasKey('data', $parsed_response);
    $this->assertCount(1, $parsed_response['data']);
    $this->assertEquals(3, $parsed_response['data'][0]['attributes']['internalId']);
    $this->assertArrayHasKey('included', $parsed_response);
    $this->assertGreaterThan(0, count($parsed_response['included']));
    // Make sure related resources don't fail.
    $response = $this->drupalGet('/api/articles/' . $this->nodes[0]->uuid() . '/owner');
    $parsed_response = Json::decode($response);
    $this->assertArrayHasKey('data', $parsed_response);
    $this->assertEquals('user--user', $parsed_response['data']['type']);

    // 2. Merge default filters with explicit filters.
    $response = $this->drupalGet('/api/articles', [
      'query' => [
        'filter' => [
          'i' => [
            'condition' => [
              'path' => 'internalId',
              'value' => '2',
            ],
          ],
        ],
      ],
    ]);
    $parsed_response = Json::decode($response);
    $this->assertArrayHasKey('data', $parsed_response);
    // internalId cannot be 2 and 3 at the same time.
    $this->assertCount(0, $parsed_response['data']);

    // 3. Override the default includes.
    $response = $this->drupalGet('/api/articles', [
      'query' => ['include' => ''],
    ]);
    $parsed_response = Json::decode($response);
    $this->assertArrayNotHasKey('included', $parsed_response);

    // 4. Using the default sorting check the order.
    // Unset filters of resource config in this test as those limit the results.
    $this->setResouceConfigValue(['default_filter' => []]);
    $this->nodes[0]->setTitle('a');
    $this->nodes[0]->save();

    $this->nodes[1]->setTitle('b');
    $this->nodes[1]->save();

    $this->nodes[2]->setTitle('c');
    $this->nodes[2]->save();

    $this->nodes[3]->setTitle('d');
    $this->nodes[3]->save();

    $response = $this->drupalGet('/api/articles');
    $parsed_response = Json::decode($response);

    // Check if order is as expected.
    $this->assertEquals('d', $parsed_response['data'][0]['attributes']['title']);
    $this->assertEquals('c', $parsed_response['data'][1]['attributes']['title']);
    $this->assertEquals('b', $parsed_response['data'][2]['attributes']['title']);
    $this->assertEquals('a', $parsed_response['data'][3]['attributes']['title']);

    // 5. Override default sorting with explicit sorting.
    $response = $this->drupalGet('/api/articles', [
      'query' => [
        'sort' => [
          'title' => [
            'path' => 'title',
            'direction' => 'ASC',
          ],
        ],
      ],
    ]);
    $parsed_response = Json::decode($response);

    // Check if order changed as expected.
    $this->assertEquals('a', $parsed_response['data'][0]['attributes']['title']);
    $this->assertEquals('b', $parsed_response['data'][1]['attributes']['title']);
    $this->assertEquals('c', $parsed_response['data'][2]['attributes']['title']);
    $this->assertEquals('d', $parsed_response['data'][3]['attributes']['title']);
  }

  /**
   * Checks standard pagination and page limit overrides.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPagination() {
    // Unset filters of resource config in this test as those limit the results.
    $this->setResouceConfigValue(['default_filter' => []]);
    $this->createDefaultContent(300, 1, FALSE, TRUE, static::IS_NOT_MULTILINGUAL);

    // 1. Check pagination using default page limit of jsonapi.
    $response = $this->drupalGet('/api/articles');
    $this->assertPagination(Json::decode($response), OffsetPage::SIZE_MAX);

    // 2. Check with an increased page limit.
    $this->setResouceConfigValue(['page_limit' => static::PAGE_LIMIT_OVERRIDE_VALUE]);
    $response = $this->drupalGet('/api/articles', [
      'query' => ['page[limit]' => static::PAGE_LIMIT_OVERRIDE_VALUE],
    ]);
    $this->assertPagination(Json::decode($response), static::PAGE_LIMIT_OVERRIDE_VALUE);

    // 3. Make sure query values higher than the configured limit won't yield
    // more results.
    $query_override = static::PAGE_LIMIT_OVERRIDE_VALUE + OffsetPage::SIZE_MAX;
    $response = $this->drupalGet('/api/articles', [
      'query' => ['page[limit]' => $query_override],
    ]);
    $response = Json::decode($response);
    $this->assertArrayHasKey('data', $response);
    $this->assertNotEquals(count($response['data']), $query_override);
    $this->assertEquals(count($response['data']), static::PAGE_LIMIT_OVERRIDE_VALUE);
  }

  /**
   * Checks if pagination links on a jsonapi response are working as expected.
   *
   * @param array $jsonapi_response
   *   The parsed response from the jsonapi endpoint.
   * @param int $page_limit
   *   Limit for amount of items displayed per page.
   */
  protected function assertPagination(array $jsonapi_response, $page_limit) {
    $this->assertArrayHasKey('data', $jsonapi_response);
    $this->assertCount($page_limit, $jsonapi_response['data']);
    $first_node_uuid = $jsonapi_response['data'][0]['attributes']['internalId'];
    $this->assertArrayHasKey('links', $jsonapi_response);
    $this->assertArrayHasKey('next', $jsonapi_response['links']);
    $this->assertArrayNotHasKey('prev', $jsonapi_response['links']);
    $this->assertPagerLink(
      $jsonapi_response['links']['next']['href'],
      1,
      $page_limit
    );

    $response = $this->drupalGet($jsonapi_response['links']['next']['href']);
    $jsonapi_response = Json::decode($response);
    $this->assertCount($page_limit, $jsonapi_response['data']);
    $this->assertNotEquals($first_node_uuid, $jsonapi_response['data'][0]['attributes']['internalId']);
    $this->assertArrayHasKey('next', $jsonapi_response['links']);
    $this->assertArrayHasKey('prev', $jsonapi_response['links']);
    $this->assertPagerLink(
      $jsonapi_response['links']['next']['href'],
      2,
      $page_limit
    );
    $this->assertPagerLink(
      $jsonapi_response['links']['prev']['href'],
      0,
      $page_limit
    );
  }

  /**
   * Asserts a pager link with a given url.
   *
   * @param string $url
   *   The url of the checked pager link.
   * @param int $page
   *   The page number the link is pointing to.
   * @param int $page_limit
   *   Limit for amount of items displayed per page.
   */
  protected function assertPagerLink($url, $page, $page_limit) {
    $query = parse_url($url, PHP_URL_QUERY);
    $query_params = Query::parse($query);
    $this->assertArrayHasKey('page[limit]', $query_params);
    $this->assertArrayHasKey('page[offset]', $query_params);
    $this->assertEquals($query_params['page[offset]'], $page * $page_limit);
    $this->assertEquals($query_params['page[limit]'], $page_limit);
  }

  /**
   * Sets given key value combination on a resource config entity.
   *
   * @param array $values
   *   Combination of keys and values to set on the resource config.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setResouceConfigValue(array $values) {
    $resource_config = JsonapiResourceConfig::load('node--article');
    foreach ($values as $key => $value) {
      $resource_config->setThirdPartySetting('jsonapi_defaults', $key, $value);
    }
    $resource_config->save();
  }

  /**
   * Creates the JSON API Resource Config entities to override the resources.
   */
  protected static function overrideResources() {
    // Override paths and fields in the articles resource.
    JsonapiResourceConfig::create([
      'id' => 'node--article',
      'third_party_settings' => [
        'jsonapi_defaults' => [
          'default_filter' => [
            'filter:nidFilter#condition#path' => 'internalId',
            'filter:nidFilter#condition#value' => 3,
          ],
          'default_sorting' => [
            'sort:title#path' => 'title',
            'sort:title#direction' => 'DESC',
          ],
          // @todo Change this to 'tags.vid'.
          'default_include' => ['tags'],
        ],
      ],
      'disabled' => FALSE,
      'path' => 'articles',
      'resourceType' => 'articles',
      'resourceFields' => [
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
  }

}
