<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\entity_share_client\Entity\RemoteInterface;
use Drupal\entity_share_server\Entity\ChannelInterface;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;

/**
 * Functional test class for import with "Anonymous" authorization.
 *
 * @group entity_share
 * @group entity_share_client
 */
class AuthenticationAnonymousTest extends AuthenticationTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Role::load(AccountInterface::ANONYMOUS_ROLE)
      ->grantPermission(ChannelInterface::CHANNELS_ACCESS_PERMISSION)
      ->save();

    foreach ($this->channels as $channel) {
      $channel->set('authorized_roles', ['anonymous']);
      $channel->save();
    }

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function createAuthenticationPlugin(UserInterface $user, RemoteInterface $remote) {
    $plugin = $this->authPluginManager->createInstance('anonymous');
    $configuration = $plugin->getConfiguration();
    $configuration['data'] = [
      'credential_provider' => 'entity_share',
      'storage_key' => $configuration['uuid'],
    ];
    $plugin->setConfiguration($configuration);
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'file' => [
        'en' => $this->preparePhysicalFilesAndFileEntitiesData(),
      ],
      'node' => [
        'en' => [
          'es_test_node_import_published' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
          'es_test_node_import_not_published' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::NOT_PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
          'es_test_node_import_with_file' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
            'field_es_test_file' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'private_file'),
                  ],
                ];
              },
              'checker_callback' => 'getFilteredStructureValues',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test that correct entities are created with "Anonymous" authorization.
   */
  public function testImport() {
    $this->pullChannel('node_es_test_en');

    // Assertions.
    $entity_storage = $this->entityTypeManager->getStorage('node');

    $published = $entity_storage->loadByProperties(['uuid' => 'es_test_node_import_published']);
    $this->assertEquals(1, count($published), 'The published node was imported.');

    $not_published = $entity_storage->loadByProperties(['uuid' => 'es_test_node_import_not_published']);
    $this->assertEquals(0, count($not_published), 'The unpublished node was not imported.');

    foreach (static::$filesData as $file_data) {
      $this->assertTrue(file_exists($file_data['uri']), 'The private physical file ' . $file_data['filename'] . ' has been pulled and recreated.');
      $file_content = file_get_contents($file_data['uri']);
      $this->assertEquals($file_data['file_content'], $file_content, 'Private physical file was downloaded with correct content.');
    }
  }

}
