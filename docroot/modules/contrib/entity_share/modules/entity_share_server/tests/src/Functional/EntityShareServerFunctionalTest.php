<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_server\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\node\NodeInterface;

/**
 * General functional test class.
 *
 * @group entity_share
 * @group entity_share_server
 */
class EntityShareServerFunctionalTest extends EntityShareServerFunctionalTestBase {

  /**
   * Test that a channel provides correct URLs.
   */
  public function testBasicChannel() {
    // Prepare a node and its translation.
    $node = $this->createNode([
      'type' => 'es_test',
      'uuid' => 'es_test',
      'title' => 'Entity share test 1 en',
      'status' => NodeInterface::PUBLISHED,
    ]);

    $node->addTranslation('fr', [
      'title' => 'Entity share test 1 fr',
    ]);
    $node->save();

    // Create channels.
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $es_test_en_channel = $channel_storage->create([
      'id' => 'es_test_en',
      'label' => 'Entity share test en',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $es_test_en_channel->save();

    $es_test_fr_channel = $channel_storage->create([
      'id' => 'es_test_fr',
      'label' => 'Entity share test fr',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'fr',
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $es_test_fr_channel->save();

    $entity_share_entrypoint_url = Url::fromRoute('entity_share_server.resource_list');
    $response = $this->request('GET', $entity_share_entrypoint_url, $this->getAuthenticationRequestOptions($this->channelUser));
    $entity_share_endpoint_response = Json::decode((string) $response->getBody());
    $expected_search_configuration = [
      'label' => [
        'path' => 'title',
        'label' => 'Label',
      ],
    ];

    // Test that the field_mapping entry exists.
    $this->assertTrue(isset($entity_share_endpoint_response['data']['field_mappings']), 'The field mappings has been found');

    // Test the english channel info.
    $this->assertTrue(isset($entity_share_endpoint_response['data']['channels']['es_test_en']), 'The english channel has been found');
    $this->assertEquals($es_test_en_channel->label(), $entity_share_endpoint_response['data']['channels']['es_test_en']['label'], 'The expected channel label has been found.');
    $this->assertEquals($es_test_en_channel->get('channel_entity_type'), $entity_share_endpoint_response['data']['channels']['es_test_en']['channel_entity_type'], 'The expected channel entity type has been found.');
    $this->assertEquals($expected_search_configuration, $entity_share_endpoint_response['data']['channels']['es_test_en']['search_configuration'], 'The expected search configuration had been found.');

    // Test that the node can be found on the channel URL.
    $response = $this->request('GET', Url::fromUri($entity_share_endpoint_response['data']['channels']['es_test_en']['url']), $this->getAuthenticationRequestOptions($this->channelUser));
    $es_test_en_channel_url_response = Json::decode((string) $response->getBody());
    $this->assertEquals($node->label(), $es_test_en_channel_url_response['data'][0]['attributes']['title'], 'The channel url is correct. The created node has been found.');

    // Test that the channel URL uuid contains only changed timestamp.
    $response = $this->request('GET', Url::fromUri($entity_share_endpoint_response['data']['channels']['es_test_en']['url_uuid']), $this->getAuthenticationRequestOptions($this->channelUser));
    $es_test_en_channel_url_uuid_response = Json::decode((string) $response->getBody());
    $this->assertEquals(1, count($es_test_en_channel_url_uuid_response['data'][0]['attributes']), 'There is only one attribute.');
    $this->assertTrue(isset($es_test_en_channel_url_uuid_response['data'][0]['attributes']['changed']), 'The only attribute is changed.');

    // Test the French channel info.
    $this->assertTrue(isset($entity_share_endpoint_response['data']['channels']['es_test_fr']), 'The French channel has been found');
    $this->assertEquals($es_test_fr_channel->label(), $entity_share_endpoint_response['data']['channels']['es_test_fr']['label'], 'The expected channel label has been found.');
    $this->assertEquals($es_test_fr_channel->get('channel_entity_type'), $entity_share_endpoint_response['data']['channels']['es_test_fr']['channel_entity_type'], 'The expected channel entity type has been found.');
    $this->assertEquals($expected_search_configuration, $entity_share_endpoint_response['data']['channels']['es_test_fr']['search_configuration'], 'The expected search configuration had been found.');

    // Test that the node translation can be found on the channel URL.
    $response = $this->request('GET', Url::fromUri($entity_share_endpoint_response['data']['channels']['es_test_fr']['url']), $this->getAuthenticationRequestOptions($this->channelUser));
    $es_test_fr_channel_url_response = Json::decode((string) $response->getBody());
    $this->assertEquals($node->getTranslation('fr')->label(), $es_test_fr_channel_url_response['data'][0]['attributes']['title'], 'The channel url is correct. The created node has been found.');
  }

  /**
   * Test filters, groups of filters, and sorts.
   */
  public function testFilteringAndSortingOnChannel() {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = $this->container->get('date.formatter');

    $timestamp_node_1 = '5000000';
    $timestamp_node_2 = '6000000';
    $timestamp_node_3 = '7000000';
    // @codingStandardsIgnoreStart
    // Expected: node 1.
    $timestamp_smaller_than_value = $timestamp_node_2;
    // Expected: node 1 and node 2.
    $timestamp_smaller_than_or_equal_value = $timestamp_node_2;
    // Expected: node 3.
    $timestamp_greater_than_value = $timestamp_node_2;
    // Expected: node 2 and node 3.
    $timestamp_greater_than_or_equal_value = $timestamp_node_2;
    // @codingStandardsIgnoreEnd

    // Create 3 tags.
    $tag_1 = $term_storage->create([
      'langcode' => 'en',
      'vid' => 'es_test',
      'name' => 'Tag 1',
    ]);
    $tag_1->save();
    $tag_2 = $term_storage->create([
      'langcode' => 'en',
      'vid' => 'es_test',
      'name' => 'Tag 2',
    ]);
    $tag_2->save();
    $tag_3 = $term_storage->create([
      'langcode' => 'en',
      'vid' => 'es_test',
      'name' => 'Tag 3',
    ]);
    $tag_3->save();

    // @codingStandardsIgnoreStart
    // Prepare nodes.
    $this->createNode([
      'type' => 'es_test',
      'uuid' => 'es_test_1',
      'title' => 'Foo Bar Test',
      'status' => NodeInterface::PUBLISHED,
      'promote' => NodeInterface::PROMOTED,
      'sticky' => NodeInterface::STICKY,
      'field_es_test_date' => $date_formatter->format($timestamp_node_1, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      'field_es_test_number_integer' => 2,
      'field_es_test_taxonomy' => [
        'target_id' => $tag_1->id(),
      ],
      'field_es_test_text_plain' => 'not null',
    ]);
    $this->createNode([
      'type' => 'es_test',
      'uuid' => 'es_test_2',
      'title' => 'Foo Contains Test',
      'status' => NodeInterface::PUBLISHED,
      'promote' => NodeInterface::PROMOTED,
      'sticky' => NodeInterface::NOT_STICKY,
      'field_es_test_date' => $date_formatter->format($timestamp_node_2, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      'field_es_test_number_integer' => 4,
      'field_es_test_taxonomy' => [
        'target_id' => $tag_2->id(),
      ],
    ]);
    $this->createNode([
      'type' => 'es_test',
      'uuid' => 'es_test_3',
      'title' => 'Bar Test',
      'status' => NodeInterface::PUBLISHED,
      'promote' => NodeInterface::NOT_PROMOTED,
      'sticky' => NodeInterface::STICKY,
      'field_es_test_date' => $date_formatter->format($timestamp_node_3, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      'field_es_test_number_integer' => 6,
      'field_es_test_taxonomy' => [
        'target_id' => $tag_3->id(),
      ],
    ]);
    // @codingStandardsIgnoreEnd

    // 1: =: Basic field: Promoted node.
    $channel_1 = $channel_storage->create([
      'id' => 'channel_1',
      'label' => 'Channel 1',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'promote' => [
          'path' => 'promote',
          'operator' => '=',
          'value' => [
            NodeInterface::PROMOTED,
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_1->save();
    $this->checkEntitiesOnChannel($channel_1->id(), [
      'es_test_1' => TRUE,
      'es_test_2' => TRUE,
      'es_test_3' => FALSE,
    ]);

    // 2: =: Entity reference: Node with a specific tag.
    $channel_2 = $channel_storage->create([
      'id' => 'channel_2',
      'label' => 'Channel 2',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'field_es_test_taxonomy_entity_name' => [
          'path' => 'field_es_test_taxonomy.entity.name',
          'operator' => '=',
          'value' => [
            'tag 1',
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_2->save();
    $this->checkEntitiesOnChannel($channel_2->id(), [
      'es_test_1' => TRUE,
      'es_test_2' => FALSE,
      'es_test_3' => FALSE,
    ]);

    // 3: <>.
    $channel_3 = $channel_storage->create([
      'id' => 'channel_3',
      'label' => 'Channel 3',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'field_es_test_taxonomy_entity_name' => [
          'path' => 'field_es_test_taxonomy.entity.name',
          'operator' => '<>',
          'value' => [
            'tag 1',
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_3->save();
    $this->checkEntitiesOnChannel($channel_3->id(), [
      'es_test_1' => FALSE,
      'es_test_2' => TRUE,
      'es_test_3' => TRUE,
    ]);

    // @codingStandardsIgnoreStart
    // 4: >.
    $channel_4 = $channel_storage->create([
      'id' => 'channel_4',
      'label' => 'Channel 4',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'field_es_test_date' => [
          'path' => 'field_es_test_date',
          'operator' => '>',
          'value' => [
            $date_formatter->format($timestamp_greater_than_value, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_4->save();
    $this->checkEntitiesOnChannel($channel_4->id(), [
      'es_test_1' => FALSE,
      'es_test_2' => FALSE,
      'es_test_3' => TRUE,
    ]);

    // 5: >=.
    $channel_5 = $channel_storage->create([
      'id' => 'channel_5',
      'label' => 'Channel 5',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'field_es_test_date' => [
          'path' => 'field_es_test_date',
          'operator' => '>=',
          'value' => [
            $date_formatter->format($timestamp_greater_than_or_equal_value, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_5->save();
    $this->checkEntitiesOnChannel($channel_5->id(), [
      'es_test_1' => FALSE,
      'es_test_2' => TRUE,
      'es_test_3' => TRUE,
    ]);

    // 6: <.
    $channel_6 = $channel_storage->create([
      'id' => 'channel_6',
      'label' => 'Channel 6',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'field_es_test_date' => [
          'path' => 'field_es_test_date',
          'operator' => '<',
          'value' => [
            $date_formatter->format($timestamp_smaller_than_value, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_6->save();
    $this->checkEntitiesOnChannel($channel_6->id(), [
      'es_test_1' => TRUE,
      'es_test_2' => FALSE,
      'es_test_3' => FALSE,
    ]);

    // 7: <=.
    $channel_7 = $channel_storage->create([
      'id' => 'channel_7',
      'label' => 'Channel 7',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'field_es_test_date' => [
          'path' => 'field_es_test_date',
          'operator' => '<=',
          'value' => [
            $date_formatter->format($timestamp_smaller_than_or_equal_value, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_7->save();
    $this->checkEntitiesOnChannel($channel_7->id(), [
      'es_test_1' => TRUE,
      'es_test_2' => TRUE,
      'es_test_3' => FALSE,
    ]);
    // @codingStandardsIgnoreEnd

    // 8: STARTS_WITH.
    $channel_8 = $channel_storage->create([
      'id' => 'channel_8',
      'label' => 'Channel 8',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'title' => [
          'path' => 'title',
          'operator' => 'STARTS_WITH',
          'value' => [
            'Foo',
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_8->save();
    $this->checkEntitiesOnChannel($channel_8->id(), [
      'es_test_1' => TRUE,
      'es_test_2' => TRUE,
      'es_test_3' => FALSE,
    ]);

    // 9: CONTAINS.
    $channel_9 = $channel_storage->create([
      'id' => 'channel_9',
      'label' => 'Channel 9',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'title' => [
          'path' => 'title',
          'operator' => 'CONTAINS',
          'value' => [
            'Contains',
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_9->save();
    $this->checkEntitiesOnChannel($channel_9->id(), [
      'es_test_1' => FALSE,
      'es_test_2' => TRUE,
      'es_test_3' => FALSE,
    ]);

    // 10: ENDS_WITH.
    $channel_10 = $channel_storage->create([
      'id' => 'channel_10',
      'label' => 'Channel 10',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'title' => [
          'path' => 'title',
          'operator' => 'ENDS_WITH',
          'value' => [
            'Bar Test',
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_10->save();
    $this->checkEntitiesOnChannel($channel_10->id(), [
      'es_test_1' => TRUE,
      'es_test_2' => FALSE,
      'es_test_3' => TRUE,
    ]);

    // 11: IN.
    $channel_11 = $channel_storage->create([
      'id' => 'channel_11',
      'label' => 'Channel 11',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'field_es_test_taxonomy_entity_name' => [
          'path' => 'field_es_test_taxonomy.entity.name',
          'operator' => 'IN',
          'value' => [
            'Tag 1',
            'Tag 2',
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_11->save();
    $this->checkEntitiesOnChannel($channel_11->id(), [
      'es_test_1' => TRUE,
      'es_test_2' => TRUE,
      'es_test_3' => FALSE,
    ]);

    // 12: NOT IN.
    $channel_12 = $channel_storage->create([
      'id' => 'channel_12',
      'label' => 'Channel 12',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'field_es_test_taxonomy_entity_name' => [
          'path' => 'field_es_test_taxonomy.entity.name',
          'operator' => 'NOT IN',
          'value' => [
            'Tag 1',
            'Tag 2',
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_12->save();
    $this->checkEntitiesOnChannel($channel_12->id(), [
      'es_test_1' => FALSE,
      'es_test_2' => FALSE,
      'es_test_3' => TRUE,
    ]);

    // 13: BETWEEN.
    $channel_13 = $channel_storage->create([
      'id' => 'channel_13',
      'label' => 'Channel 13',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'field_es_test_number_integer' => [
          'path' => 'field_es_test_number_integer',
          'operator' => 'BETWEEN',
          'value' => [
            '3',
            '5',
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_13->save();
    $this->checkEntitiesOnChannel($channel_13->id(), [
      'es_test_1' => FALSE,
      'es_test_2' => TRUE,
      'es_test_3' => FALSE,
    ]);

    // 14: NOT BETWEEN.
    $channel_14 = $channel_storage->create([
      'id' => 'channel_14',
      'label' => 'Channel 14',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'field_es_test_number_integer' => [
          'path' => 'field_es_test_number_integer',
          'operator' => 'NOT BETWEEN',
          'value' => [
            '3',
            '5',
          ],
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_14->save();
    $this->checkEntitiesOnChannel($channel_14->id(), [
      'es_test_1' => TRUE,
      'es_test_2' => FALSE,
      'es_test_3' => TRUE,
    ]);

    // 15: IS NULL.
    $channel_15 = $channel_storage->create([
      'id' => 'channel_15',
      'label' => 'Channel 15',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'field_es_test_text_plain' => [
          'path' => 'field_es_test_text_plain',
          'operator' => 'IS NULL',
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_15->save();
    $this->checkEntitiesOnChannel($channel_15->id(), [
      'es_test_1' => FALSE,
      'es_test_2' => TRUE,
      'es_test_3' => TRUE,
    ]);

    // 16: IS NOT NULL.
    $channel_16 = $channel_storage->create([
      'id' => 'channel_16',
      'label' => 'Channel 16',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'field_es_test_text_plain' => [
          'path' => 'field_es_test_text_plain',
          'operator' => 'IS NOT NULL',
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_16->save();
    $this->checkEntitiesOnChannel($channel_16->id(), [
      'es_test_1' => TRUE,
      'es_test_2' => FALSE,
      'es_test_3' => FALSE,
    ]);

    // 17: Grouping grouped filters.
    $channel_17 = $channel_storage->create([
      'id' => 'channel_17',
      'label' => 'Channel 17',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_filters' => [
        'promote' => [
          'path' => 'promote',
          'operator' => '=',
          'value' => [
            NodeInterface::PROMOTED,
          ],
          'memberof' => 'or_group',
        ],
        'sticky' => [
          'path' => 'sticky',
          'operator' => '=',
          'value' => [
            NodeInterface::STICKY,
          ],
          'memberof' => 'or_group',
        ],
        'field_es_test_text_plain' => [
          'path' => 'field_es_test_text_plain',
          'operator' => 'IS NULL',
          'memberof' => 'and_group',
        ],
      ],
      'channel_groups' => [
        'and_group' => [
          'conjunction' => 'AND',
        ],
        'or_group' => [
          'conjunction' => 'OR',
          'memberof' => 'and_group',
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_17->save();
    $this->checkEntitiesOnChannel($channel_17->id(), [
      'es_test_1' => FALSE,
      'es_test_2' => TRUE,
      'es_test_3' => TRUE,
    ]);

    // 18: Sorts.
    $channel_18 = $channel_storage->create([
      'id' => 'channel_18',
      'label' => 'Channel 18',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_sorts' => [
        'promote' => [
          'path' => 'promote',
          'direction' => 'ASC',
          'weight' => -10,
        ],
        'field_es_test_date' => [
          'path' => 'field_es_test_date',
          'direction' => 'DESC',
          'weight' => -9,
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $channel_18->save();
    $this->checkEntitiesOrderOnChannel($channel_18->id(), [
      'es_test_3',
      'es_test_2',
      'es_test_1',
    ]);
  }

  /**
   * Test that a channel provides correct search configuration.
   */
  public function testSearchConfiguration() {
    // Create channels.
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $es_test_en_channel = $channel_storage->create([
      'id' => 'es_test_en',
      'label' => 'Entity share test en',
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'channel_searches' => [
        'field_es_test_taxonomy_entity_name' => [
          'path' => 'field_es_test_taxonomy.entity.name',
          'label' => 'Tag name',
        ],
      ],
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser->uuid(),
      ],
    ]);
    $es_test_en_channel->save();

    $entity_share_entrypoint_url = Url::fromRoute('entity_share_server.resource_list');
    $response = $this->request('GET', $entity_share_entrypoint_url, $this->getAuthenticationRequestOptions($this->channelUser));
    $entity_share_endpoint_response = Json::decode((string) $response->getBody());
    $expected_search_configuration = [
      'label' => [
        'path' => 'title',
        'label' => 'Label',
      ],
      'field_es_test_taxonomy_entity_name' => [
        'path' => 'field_es_test_taxonomy.entity.name',
        'label' => 'Tag name',
      ],
    ];

    $this->assertEquals($expected_search_configuration, $entity_share_endpoint_response['data']['channels']['es_test_en']['search_configuration'], 'The expected search configuration had been found.');
  }

  /**
   * Test limiting number of entities displayed on channel.
   */
  public function testChannelMaxSize() {
    for ($i = 1; $i <= 60; $i++) {
      $this->createNode([
        'type' => 'es_test',
        'title' => "Entity share test $i en",
        'status' => NodeInterface::PUBLISHED,
      ]);
    }

    $this->checkChannelNumberOfResults(50);
    $this->checkChannelNumberOfResults(30);
  }

  /**
   * Helper function to check the number of entities on a specific channel.
   *
   * @param int $maxSize
   *   The channel max size to set and so to expect.
   */
  protected function checkChannelNumberOfResults($maxSize) {
    $channel_storage = $this->entityTypeManager->getStorage('channel');

    $es_test_en_channel = $channel_storage->load('es_test_en');
    if (is_null($es_test_en_channel)) {
      $es_test_en_channel = $channel_storage->create([
        'id' => 'es_test_en',
        'label' => 'Entity share test en',
        'channel_maxsize' => $maxSize,
        'channel_entity_type' => 'node',
        'channel_bundle' => 'es_test',
        'channel_langcode' => 'en',
        'channel_searches' => [],
        'access_by_permission' => FALSE,
        'authorized_roles' => [],
        'authorized_users' => [
          $this->channelUser->uuid(),
        ],
      ]);
    }
    else {
      $es_test_en_channel->set('channel_maxsize', 30);
    }
    $es_test_en_channel->save();

    $entity_share_entrypoint_url = Url::fromRoute('entity_share_server.resource_list');
    $response = $this->request('GET', $entity_share_entrypoint_url, $this->getAuthenticationRequestOptions($this->channelUser));
    $entity_share_endpoint_response = Json::decode((string) $response->getBody());

    // Test the number of results on the channel URL.
    $response = $this->request('GET', Url::fromUri($entity_share_endpoint_response['data']['channels']['es_test_en']['url']), $this->getAuthenticationRequestOptions($this->channelUser));
    $channel_url_response = Json::decode((string) $response->getBody());
    $channel_url_data = EntityShareUtility::prepareData($channel_url_response['data']);

    $this->assertEquals($maxSize, count($channel_url_data));
  }

  /**
   * Helper function to check the presence of entities on a specific channel.
   *
   * @param string $channel_id
   *   The channel id on which to check the entities.
   * @param array $entity_uuids
   *   The entity UUIDs to check for. Key is the entity UUID and the value is
   *   the expected status.
   */
  protected function checkEntitiesOnChannel($channel_id, array $entity_uuids) {
    $entity_share_entrypoint_url = Url::fromRoute('entity_share_server.resource_list');
    $response = $this->request('GET', $entity_share_entrypoint_url, $this->getAuthenticationRequestOptions($this->channelUser));
    $entity_share_endpoint_response = Json::decode((string) $response->getBody());

    // Test that the node can be found on the channel URL.
    $response = $this->request('GET', Url::fromUri($entity_share_endpoint_response['data']['channels'][$channel_id]['url']), $this->getAuthenticationRequestOptions($this->channelUser));
    $channel_url_response = Json::decode((string) $response->getBody());
    $channel_url_data = EntityShareUtility::prepareData($channel_url_response['data']);

    foreach ($entity_uuids as $entity_uuid => $expected) {
      $found = FALSE;
      foreach ($channel_url_data as $entity_data) {
        if ($entity_data['id'] == $entity_uuid) {
          $found = TRUE;
        }
      }

      $this->assertEquals($expected, $found, 'Expected state for entity with UUID: ' . $entity_uuid);
    }
  }

  /**
   * Helper function to check the order of entities on a specific channel.
   *
   * @param string $channel_id
   *   The channel id on which to check the entities.
   * @param array $entity_uuids
   *   The entity UUIDs to check for.
   */
  protected function checkEntitiesOrderOnChannel($channel_id, array $entity_uuids) {
    $entity_share_entrypoint_url = Url::fromRoute('entity_share_server.resource_list');
    $response = $this->request('GET', $entity_share_entrypoint_url, $this->getAuthenticationRequestOptions($this->channelUser));
    $entity_share_endpoint_response = Json::decode((string) $response->getBody());

    // Test that the node can be found on the channel URL.
    $response = $this->request('GET', Url::fromUri($entity_share_endpoint_response['data']['channels'][$channel_id]['url']), $this->getAuthenticationRequestOptions($this->channelUser));
    $channel_url_response = Json::decode((string) $response->getBody());
    $channel_url_data = EntityShareUtility::prepareData($channel_url_response['data']);

    foreach ($entity_uuids as $entity_position => $entity_uuid) {
      $found = FALSE;
      if ($channel_url_data[$entity_position]['id'] == $entity_uuid) {
        $found = TRUE;
      }

      $this->assertTrue($found, 'Correct expected position for entity with UUID: ' . $entity_uuid);
    }
  }

}
