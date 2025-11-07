<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Kernel;

use Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests remote url.
 *
 * @group entity_share
 * @group entity_share_client
 */
class RemoteUrlTest extends EntityKernelTestBase {

  /**
   * Injected plugin service.
   *
   * @var \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationPluginManager
   */
  protected $authPluginManager;

  /**
   * The key value store to use.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValueStore;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'jsonapi',
    'entity_share_client',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->authPluginManager = $this->container->get('plugin.manager.entity_share_client_authorization');
    $this->keyValueStore = $this->container->get('keyvalue')->get(ClientAuthorizationInterface::LOCAL_STORAGE_KEY_VALUE_COLLECTION);
    $this->installEntitySchema('remote');
  }

  /**
   * Tests that trailing slash is removed from url.
   */
  public function testRemotePreSave() {
    $remote_storage = $this->entityTypeManager->getStorage('remote');
    $plugin = $this->authPluginManager->createInstance('basic_auth');
    $configuration = $plugin->getConfiguration();
    $remote = $remote_storage->create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'url' => 'http://example.com',
    ]);
    $credentials = [];
    $credentials['username'] = 'test';
    $credentials['password'] = 'test';
    $this->keyValueStore->set($configuration['uuid'], $credentials);
    $key = $configuration['uuid'];
    $configuration['data'] = [
      'credential_provider' => 'entity_share',
      'storage_key' => $key,
    ];
    $plugin->setConfiguration($configuration);
    $remote->mergePluginConfig($plugin);
    $remote->save();

    $this->assertEquals('http://example.com', $remote->get('url'));

    $remote->set('url', 'http://example.com/');
    $remote->save();
    $this->assertEquals('http://example.com', $remote->get('url'));

    $remote->set('url', 'http://example.com/subdirectory');
    $remote->save();
    $this->assertEquals('http://example.com/subdirectory', $remote->get('url'));

    $remote->set('url', 'http://example.com/subdirectory/');
    $remote->save();
    $this->assertEquals('http://example.com/subdirectory', $remote->get('url'));
  }

}
