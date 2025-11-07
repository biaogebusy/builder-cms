<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_server\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Test class for entity share endpoint and channel access.
 *
 * @group entity_share
 * @group entity_share_server
 */
class ChannelAccessTest extends EntityShareServerFunctionalTestBase {

  /**
   * A test user with access to the channel list.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $channelUser2;

  /**
   * A test user with access to the channel list.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $channelUser3;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->channelUser2 = $this->drupalCreateUser($this->getChannelUserPermissions());
    $this->channelUser3 = $this->drupalCreateUser($this->getChannelUserPermissions());
  }

  /**
   * Test the channel access.
   */
  public function testChannelAccess() {
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel_storage->create([
      'id' => 'es_test_permission',
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'access_by_permission' => TRUE,
      'authorized_roles' => [],
      'authorized_users' => [],
    ])->save();

    $channel_storage->create([
      'id' => 'es_test_role',
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'access_by_permission' => FALSE,
      'authorized_roles' => $this->channelUser->getRoles(TRUE),
      'authorized_users' => [],
    ])->save();

    $channel_storage->create([
      'id' => 'es_test_user',
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'en',
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $this->channelUser2->uuid(),
        $this->channelUser3->uuid(),
      ],
    ])->save();

    $this->checkAccessibleChannels($this->channelUser, [
      'es_test_permission',
      'es_test_role',
    ], [
      'es_test_user',
    ]);
    $this->checkAccessibleChannels($this->channelUser2, [
      'es_test_permission',
      'es_test_user',
    ], [
      'es_test_role',
    ]);
    $this->checkAccessibleChannels($this->channelUser3, [
      'es_test_permission',
      'es_test_user',
    ], [
      'es_test_role',
    ]);
  }

  /**
   * Check if channel info is present or not.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to test against.
   * @param array $expected_accessible_channels
   *   The list of channel IDs expected to be accessible.
   * @param array $expected_forbidden_channels
   *   The list of channel IDs expected to not be accessible.
   */
  protected function checkAccessibleChannels(UserInterface $user, array $expected_accessible_channels, array $expected_forbidden_channels) {
    $entity_share_entrypoint_url = Url::fromRoute('entity_share_server.resource_list');
    $response = $this->request('GET', $entity_share_entrypoint_url, $this->getAuthenticationRequestOptions($user));
    $entity_share_endpoint_response = Json::decode((string) $response->getBody());

    foreach ($expected_accessible_channels as $channel_id) {
      $this->assertTrue(isset($entity_share_endpoint_response['data']['channels'][$channel_id]));
    }
    foreach ($expected_forbidden_channels as $channel_id) {
      $this->assertFalse(isset($entity_share_endpoint_response['data']['channels'][$channel_id]));
    }
  }

}
