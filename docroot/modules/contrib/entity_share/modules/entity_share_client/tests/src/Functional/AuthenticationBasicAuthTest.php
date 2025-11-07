<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\entity_share_server\Entity\ChannelInterface;
use Drupal\user\Entity\Role;

/**
 * Functional test class for import with "Basic Auth" authorization.
 *
 * @group entity_share
 * @group entity_share_client
 */
class AuthenticationBasicAuthTest extends AuthenticationTestBase {

  /**
   * The identifier of Key.
   *
   * @var string
   */
  protected static $keyName = 'key_basic_auth';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Role::load(AccountInterface::AUTHENTICATED_ROLE)
      ->grantPermission(ChannelInterface::CHANNELS_ACCESS_PERMISSION)
      ->save();

    // Change the initial remote configuration: it will use the admin user
    // to authenticate. We first test as administrative user because they have
    // access to all nodes, so we can in the beginning of the test pull the
    // channel and use `checkCreatedEntities()`.
    $plugin = $this->createAuthenticationPlugin($this->adminUser, $this->remote);
    $this->remote->mergePluginConfig($plugin);
    $this->remote->save();
    // Give admin user access to all channels (channel user already has it).
    foreach ($this->channels as $channel) {
      $authorized_users = $channel->get('authorized_users');
      $authorized_users = array_merge($authorized_users, [$this->adminUser->uuid()]);
      $channel->set('authorized_users', $authorized_users);
      $channel->save();
    }

    // Create Key with channel user's credentials.
    $this->createKey();

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return \array_merge([
      // Administrative user will be actually pulling the content, so we need
      // them to allow to pull unpublished nodes, unlike the channel user.
      'bypass node access',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Test that correct entities are created with different authentications.
   */
  public function testImport() {
    // 1. Test content creation as administrative
    // user: both published and unpublished nodes should be created.
    // In this run we are also testing the access to private physical files.
    // First, assert that files didn't exist before import.
    foreach (static::$filesData as $file_data) {
      $this->assertFalse(file_exists($file_data['uri']), 'The physical file ' . $file_data['filename'] . ' has been deleted.');
    }

    // Pull channel and test that all nodes and file entities are there.
    $this->pullChannel('node_es_test_en');
    $this->checkCreatedEntities();

    // Some stronger assertions for the uploaded private file.
    foreach (static::$filesData as $file_definition) {
      $this->assertTrue(file_exists($file_definition['uri']), 'The physical file ' . $file_definition['filename'] . ' has been pulled and recreated.');
      $this->assertEquals($file_definition['file_content'], file_get_contents($file_definition['uri']), 'The content of physical file ' . $file_definition['filename'] . ' is correct.');
    }

    // Delete all "client" entities created after the first import.
    $this->resetImportedContent();
    // Also clean up all uploaded files.
    foreach (static::$filesData as $file_data) {
      $this->fileSystem->delete($file_data['uri']);
    }
    // There is no need to test the physical files anymore, so we will remove
    // them from the entity array.
    unset($this->entitiesData['file']);
    unset($this->entitiesData['node']['en']['es_test_node_import_published']['field_es_test_file']);

    // 2. Test as a non-administrative user who can't access unpublished nodes.
    // Change the remote so that is uses the channel user's credentials.
    $plugin = $this->createAuthenticationPlugin($this->channelUser, $this->remote);
    $this->remote->mergePluginConfig($plugin);
    $this->remote->save();

    // Since the remote ID remains the same, we need to reset some of
    // remote manager's cached values.
    $this->resetRemoteCaches();

    // Prepare the "server" content again.
    $this->prepareContent();

    // Get channel info so that individual channels can be pulled next.
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);

    // Re-import data from JSON:API.
    $this->reimportChannel($channel_infos);

    // Assertions.
    $entity_storage = $this->entityTypeManager->getStorage('node');

    $published = $entity_storage->loadByProperties(['uuid' => 'es_test_node_import_published']);
    $this->assertEquals(1, count($published), 'The published node was imported.');

    $not_published = $entity_storage->loadByProperties(['uuid' => 'es_test_node_import_not_published']);
    $this->assertEquals(0, count($not_published), 'The unpublished node was not imported.');

    // 3. Test as non-administrative user, but with credentials stored using
    // Key module.
    $plugin = $this->remote->getAuthPlugin();
    $configuration = $plugin->getConfiguration();
    $configuration['data'] = [
      'credential_provider' => 'key',
      'storage_key' => static::$keyName,
    ];
    $plugin->setConfiguration($configuration);
    $this->remote->mergePluginConfig($plugin);
    // Save the "Remote" config entity.
    $this->remote->save();

    $this->resetImportedContent();
    $this->resetRemoteCaches();
    $this->prepareContent();

    $this->reimportChannel($channel_infos);

    // Assertions.
    $entity_storage = $this->entityTypeManager->getStorage('node');

    $published = $entity_storage->loadByProperties(['uuid' => 'es_test_node_import_published']);
    $this->assertEquals(1, count($published), 'The published node was imported.');

    $not_published = $entity_storage->loadByProperties(['uuid' => 'es_test_node_import_not_published']);
    $this->assertEquals(0, count($not_published), 'The unpublished node was not imported.');
  }

  /**
   * Create a key of Basic auth type.
   */
  protected function createKey() {
    $this->createTestKey(static::$keyName, 'entity_share_basic_auth', 'config');
    $username = $this->channelUser->getAccountName();
    $password = $this->channelUser->passRaw;
    $key_value = <<<EOT
{
  "username": "$username",
  "password": "$password"
}
EOT;
    $this->testKey->setKeyValue($key_value);
    $this->testKey->save();
  }

}
