<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel\Plugin\KeyProvider;

use Drupal\acquia_connector\Plugin\KeyProvider\AcquiaNetworkKeyProvider;
use Drupal\Component\Uuid\Php as PhpUuid;
use Drupal\Core\Site\Settings as CoreSettings;
use Drupal\key\Entity\Key;
use Drupal\Tests\acquia_connector\Kernel\AcquiaConnectorTestBase;

/**
 * Tests the AcquiaCloudKeyProvider.
 *
 * @coversClass \Drupal\acquia_connector\Plugin\KeyProvider\AcquiaNetworkKeyProvider
 * @group acquia_connector
 */
final class AcquiaNetworkKeyProviderTest extends AcquiaConnectorTestBase {

  /**
   * Additional modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'key',
  ];

  /**
   * The key provider under test.
   *
   * @var \Drupal\acquia_connector\Plugin\KeyProvider\AcquiaNetworkKeyProvider
   */
  protected $keyProvider;

  /**
   * A mock key entity.
   *
   * @var \Drupal\key\KeyInterface
   */
  protected $key;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a mock key entity.
    $this->key = Key::create([
      'id' => 'test_key',
      'label' => 'Test Key',
      'key_type' => 'authentication',
      'key_provider' => 'acquia_cloud_network',
    ]);

    // Create the key provider instance.
    $this->keyProvider = new AcquiaNetworkKeyProvider(
      [],
      'acquia_cloud_network',
      []
    );
  }

  /**
   * Tests that the provider returns NULL when not on Acquia hosting.
   */
  public function testNotOnAcquiaHosting(): void {
    // Ensure no environment variables are set.
    $this->clearAcquiaEnvironmentVariables();

    $result = $this->keyProvider->getKeyValue($this->key);
    self::assertNull($result);
  }

  /**
   * Tests that the provider returns NULL when missing environment variables.
   */
  public function testMissingEnvironmentVariables(): void {
    // Set only some environment variables.
    $this->putEnv('AH_SITE_ENVIRONMENT', 'test');
    $this->putEnv('AH_SITE_NAME', 'foo');
    // Missing AH_SITE_GROUP and AH_APPLICATION_UUID.
    $result = $this->keyProvider->getKeyValue($this->key);
    self::assertNull($result);
  }

  /**
   * Tests that the provider returns NULL for IDE environment.
   */
  public function testIdeEnvironment(): void {
    $uuid = (new PhpUuid())->generate();
    $this->setAcquiaEnvironmentVariables('ide', 'foo', 'bar', $uuid);

    // Set core settings.
    $settings = CoreSettings::getAll();
    $settings['ah_network_identifier'] = 'ABC-1234';
    $settings['ah_network_key'] = 'TEST_KEY';
    new CoreSettings($settings);

    $result = $this->keyProvider->getKeyValue($this->key);
    self::assertNull($result);
  }

  /**
   * Tests that the provider returns NULL for ODE environment.
   */
  public function testOdeEnvironment(): void {
    $uuid = (new PhpUuid())->generate();
    $this->setAcquiaEnvironmentVariables('ode', 'foo', 'bar', $uuid);

    // Set core settings.
    $settings = CoreSettings::getAll();
    $settings['ah_network_identifier'] = 'ABC-1234';
    $settings['ah_network_key'] = 'TEST_KEY';
    new CoreSettings($settings);

    $result = $this->keyProvider->getKeyValue($this->key);
    self::assertNull($result);
  }

  /**
   * Tests that the provider returns NULL when missing network settings.
   */
  public function testMissingNetworkSettings(): void {
    $uuid = (new PhpUuid())->generate();
    $this->setAcquiaEnvironmentVariables('test', 'foo', 'bar', $uuid);

    // Don't set any network settings.
    $result = $this->keyProvider->getKeyValue($this->key);
    self::assertNull($result);
  }

  /**
   * Tests key retrieval from CoreSettings only.
   */
  public function testFromCoreSettingsOnly(): void {
    $uuid = (new PhpUuid())->generate();
    $this->setAcquiaEnvironmentVariables('test', 'foo', 'bar', $uuid);

    // Set core settings.
    $settings = CoreSettings::getAll();
    $settings['ah_network_identifier'] = 'ABC-1234';
    $settings['ah_network_key'] = 'TEST_KEY';
    new CoreSettings($settings);

    $result = $this->keyProvider->getKeyValue($this->key);

    self::assertIsArray($result);
    self::assertEquals('ABC-1234', $result['ah_network_identifier']);
    self::assertEquals('TEST_KEY', $result['ah_network_key']);
    self::assertEquals($uuid, $result['ah_application_uuid']);
  }

  /**
   * Tests successful key retrieval with global config fallback.
   */
  public function testWithGlobalConfigFallback(): void {
    $uuid = (new PhpUuid())->generate();
    $this->setAcquiaEnvironmentVariables('test', 'foo', 'bar', $uuid);

    // Set global config instead of CoreSettings.
    global $config;
    $config['ah_network_identifier'] = 'GLOBAL-1234';
    $config['ah_network_key'] = 'GLOBAL_KEY';

    $result = $this->keyProvider->getKeyValue($this->key);

    self::assertIsArray($result);
    self::assertEquals('GLOBAL-1234', $result['ah_network_identifier']);
    self::assertEquals('GLOBAL_KEY', $result['ah_network_key']);
    self::assertEquals($uuid, $result['ah_application_uuid']);
  }

  /**
   * Tests CoreSettings takes precedence over global config.
   */
  public function testCoreSettingsPrecedence(): void {
    $uuid = (new PhpUuid())->generate();
    $this->setAcquiaEnvironmentVariables('test', 'foo', 'bar', $uuid);

    // Set both global config and CoreSettings.
    global $config;
    $config['ah_network_identifier'] = 'GLOBAL-1234';
    $config['ah_network_key'] = 'GLOBAL_KEY';

    $settings = CoreSettings::getAll();
    $settings['ah_network_identifier'] = 'SETTINGS-5678';
    $settings['ah_network_key'] = 'SETTINGS_KEY';
    new CoreSettings($settings);

    $result = $this->keyProvider->getKeyValue($this->key);

    self::assertIsArray($result);
    self::assertEquals('SETTINGS-5678', $result['ah_network_identifier']);
    self::assertEquals('SETTINGS_KEY', $result['ah_network_key']);
    self::assertEquals($uuid, $result['ah_application_uuid']);
  }

  /**
   * Helper method to set all required Acquia environment variables.
   *
   * @param string $environment
   *   The site environment.
   * @param string $site_name
   *   The site name.
   * @param string $site_group
   *   The site group.
   * @param string $uuid
   *   The application UUID.
   */
  private function setAcquiaEnvironmentVariables(string $environment, string $site_name, string $site_group, string $uuid): void {
    $this->putEnv('AH_SITE_ENVIRONMENT', $environment);
    $this->putEnv('AH_SITE_NAME', $site_name);
    $this->putEnv('AH_SITE_GROUP', $site_group);
    $this->putEnv('AH_APPLICATION_UUID', $uuid);
  }

  /**
   * Helper method to clear all Acquia environment variables.
   */
  private function clearAcquiaEnvironmentVariables(): void {
    $vars = ['AH_SITE_ENVIRONMENT', 'AH_SITE_NAME', 'AH_SITE_GROUP', 'AH_APPLICATION_UUID'];
    foreach ($vars as $var) {
      putenv($var);
      unset($_ENV[$var]);
      unset($_SERVER[$var]);
    }
  }

}
