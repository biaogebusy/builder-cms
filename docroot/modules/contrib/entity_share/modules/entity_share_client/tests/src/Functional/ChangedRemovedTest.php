<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Functional test class to test behavior if changed attributes is removed.
 *
 * Dedicated test class because of the setup.
 *
 * @group entity_share
 * @group entity_share_client
 */
class ChangedRemovedTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi_extras',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  protected static $entityBundleId = 'es_test';

  /**
   * {@inheritdoc}
   */
  protected static $entityLangcode = 'en';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager->getStorage('jsonapi_resource_config')->create([
      'id' => 'node--es_test',
      'disabled' => FALSE,
      'path' => 'node/es_test',
      'resourceType' => 'node--es_test',
      'resourceFields' => [
        'changed' => [
          'fieldName' => 'changed',
          'publicName' => 'changed',
          'enhancer' => [
            'id' => '',
          ],
          'disabled' => TRUE,
        ],
      ],
    ])->save();

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          'es_test' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
        'fr' => [
          'es_test' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test basic pull feature.
   *
   * Test that an entity with changed attributes removed can be pulled.
   */
  public function testBasicPull() {
    $this->pullEveryChannels();
    $this->checkCreatedEntities();
  }

  /**
   * {@inheritdoc}
   */
  protected function createChannel(UserInterface $user) {
    parent::createChannel($user);

    // Add a channel for the node in French.
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel = $channel_storage->create([
      'id' => 'node_es_test_fr',
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'fr',
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $user->uuid(),
      ],
    ]);
    $channel->save();
    $this->channels[$channel->id()] = $channel;
  }

}
