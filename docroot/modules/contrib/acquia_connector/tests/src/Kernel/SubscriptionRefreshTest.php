<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel;

use Drupal\Component\Uuid\Php as PhpUuid;

/**
 * Tests for refreshing a subscription.
 *
 * @group acquia_connector
 */
final class SubscriptionRefreshTest extends AcquiaConnectorTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system']);
    $this->config('system.site')
      ->set('uuid', (new PhpUuid())->generate())
      ->save();
  }

  /**
   * Tests acquia_connector_modules_installed().
   */
  public function testModulesInstalled(): void {
    $this->container->get('state')->setMultiple([
      'acquia_connector.identifier' => 'ABC',
      'acquia_connector.key' => 'DEF',
      'acquia_connector.application_uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
    ]);
    $this->container->get('acquia_connector.subscription')->populateSettings();

    self::assertEquals(
      [
        'active' => TRUE,
        'href' => '',
        'uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
        'subscription_name' => '',
        'expiration_date' => '',
        'product' => [
          'view' => 'Acquia Network',
        ],
        'search_service_enabled' => 1,
        'gratis' => FALSE,
      ],
      $this->container->get('acquia_connector.subscription')->getSubscription()
    );

    $this->container->get('module_installer')->install([
      'acquia_connector_subdata_test',
    ]);

    self::assertEquals(
      [
        'active' => TRUE,
        'href' => '',
        'uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
        'subscription_name' => '',
        'expiration_date' => '',
        'product' => [
          'view' => 'Acquia Network',
          'acquia_subdata_product' => [
            'foo' => 'bar',
            'data_from_subscription' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
          ],
        ],
        'search_service_enabled' => 1,
        'gratis' => FALSE,
      ],
      $this->container->get('acquia_connector.subscription')->getSubscription()
    );
  }

  /**
   * Tests acquia_connector_modules_uninstalled().
   */
  public function testModuleUninstalled(): void {
    $this->container->get('state')->setMultiple([
      'acquia_connector.identifier' => 'ABC',
      'acquia_connector.key' => 'DEF',
      'acquia_connector.application_uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
    ]);
    $this->container->get('acquia_connector.subscription')->populateSettings();

    $this->container->get('module_installer')->install([
      'acquia_connector_subdata_test',
    ]);

    self::assertEquals(
      [
        'active' => TRUE,
        'href' => '',
        'uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
        'subscription_name' => '',
        'expiration_date' => '',
        'product' => [
          'view' => 'Acquia Network',
          'acquia_subdata_product' => [
            'foo' => 'bar',
            'data_from_subscription' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
          ],
        ],
        'search_service_enabled' => 1,
        'gratis' => FALSE,

      ],
      $this->container->get('acquia_connector.subscription')->getSubscription()
    );

    $this->container->get('module_installer')->uninstall([
      'acquia_connector_subdata_test',
    ]);

    self::assertEquals(
      [
        'active' => TRUE,
        'href' => '',
        'uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
        'subscription_name' => '',
        'expiration_date' => '',
        'product' => [
          'view' => 'Acquia Network',
        ],
        'search_service_enabled' => 1,
        'gratis' => FALSE,
      ],
      $this->container->get('acquia_connector.subscription')->getSubscription()
    );

  }

  /**
   * Test getSubscription().
   */
  public function testGetSubscription(): void {
    $this->container->get('state')->setMultiple([
      'acquia_connector.identifier' => 'ABC',
      'acquia_connector.key' => 'DEF',
      'acquia_connector.application_uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
      'acquia_connector.subscription_data' => 'bogus_data',
    ]);
    $this->container->get('acquia_connector.subscription')->populateSettings();

    // Assert that we don't get data if oAuth data is empty.
    $keys = ["subscription_name", "expiration_date"];
    $subscription_data_no_oauth = $this->container->get('acquia_connector.subscription')
      ->getSubscription(TRUE);

    foreach ($keys as $key) {
      $this->assertEmpty($subscription_data_no_oauth[$key]);
    }

    // Assert again with oAuth data set.
    $this->populateOauthSettings();
    $subscription_data_with_oauth = $this->container->get('acquia_connector.subscription')
      ->getSubscription(TRUE);

    foreach ($keys as $key) {
      $this->assertNotEmpty($subscription_data_with_oauth[$key]);
    }
  }

}
