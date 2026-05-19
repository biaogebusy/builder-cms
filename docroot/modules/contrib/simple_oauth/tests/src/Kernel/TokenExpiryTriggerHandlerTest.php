<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\consumers\Entity\Consumer;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_oauth\Entity\Oauth2Token;
use Drupal\user\Entity\User;

/**
 * Kernel test for the token expiry trigger handler.
 *
 * @coversDefaultClass \Drupal\simple_oauth\TokenExpiryTriggerHandler
 * @group simple_oauth
 */
class TokenExpiryTriggerHandlerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'consumers',
    'file',
    'image',
    'serialization',
    'simple_oauth',
    'simple_oauth_test',
    'system',
    'user',
    'options',
  ];

  /**
   * Test user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $testUser;

  /**
   * Second test user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $testUser2;

  /**
   * Test consumer/client.
   *
   * @var \Drupal\consumers\Entity\Consumer
   */
  protected $testClient;

  /**
   * Second test consumer/client.
   *
   * @var \Drupal\consumers\Entity\Consumer
   */
  protected $testClient2;

  /**
   * Expired collector service.
   *
   * @var \Drupal\simple_oauth\ExpiredCollector
   */
  protected $expiredCollector;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['simple_oauth']);
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('consumer');
    $this->installEntitySchema('oauth2_token');
    $this->installEntitySchema('file');

    $this->testUser = User::create([
      'name' => $this->randomString(5),
      'pass' => $this->randomString(5),
      'status' => 1,
    ]);
    $this->testUser->save();
    $this->testUser2 = User::create([
      'name' => $this->randomString(5),
      'pass' => $this->randomString(5),
      'status' => 1,
    ]);
    $this->testUser2->save();

    $this->testClient = Consumer::create([
      'label' => $this->randomString(5),
      'client_id' => $this->randomString(5),
    ]);
    $this->testClient->save();
    $this->testClient2 = Consumer::create([
      'label' => $this->randomString(5),
      'client_id' => $this->randomString(5),
    ]);
    $this->testClient2->save();

    foreach ([$this->testUser, $this->testUser2] as $testUser) {
      foreach ([$this->testClient, $this->testClient2] as $testClient) {
        Oauth2Token::create([
          'bundle' => 'access_token',
          'auth_user_id' => $testUser->id(),
          'client' => $testClient->id(),
          'value' => $this->randomString(10),
        ])->save();
        Oauth2Token::create([
          'bundle' => 'refresh_token',
          'auth_user_id' => $testUser->id(),
          'client' => $testClient->id(),
          'value' => $this->randomString(10),
        ])->save();
      }
    }

    $this->expiredCollector = $this->container->get('simple_oauth.expired_collector');
  }

  /**
   * Check tokens invalidation when a user update occurs.
   */
  public function testOnUserUpdate() {
    // Tokens are initially available for all clients.
    $this->assertNotEmpty($this->expiredCollector->collectForAccount($this->testUser));
    $this->assertNotEmpty($this->expiredCollector->collectForAccount($this->testUser2));

    // After saving one specific user, only the associated tokens are removed.
    $this->testUser->save();
    $this->assertEmpty($this->expiredCollector->collectForAccount($this->testUser));
    $this->assertNotEmpty($this->expiredCollector->collectForAccount($this->testUser2));
  }

  /**
   * Check tokens invalidation when a consumer update occurs.
   */
  public function testOnConsumerUpdate() {
    // Tokens are initially available.
    $this->assertNotEmpty($this->expiredCollector->collectForClient($this->testClient));
    $this->assertNotEmpty($this->expiredCollector->collectForClient($this->testClient2));

    // After saving one specific client, only the associated tokens are removed.
    $this->testClient->save();
    $this->assertEmpty($this->expiredCollector->collectForClient($this->testClient));
    $this->assertNotEmpty($this->expiredCollector->collectForClient($this->testClient2));
  }

}
