<?php

namespace Drupal\Tests\acquia_connector\Unit;

use Drupal\acquia_connector\Form\SettingsForm;
use Drupal\acquia_connector\Settings;
use Drupal\acquia_connector\SiteProfile\SiteProfile;
use Drupal\acquia_connector\Subscription;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests whether settings are overridden.
 *
 * Based on network key, network identifier and app uuid.
 *
 * @group acquia_connector
 */
class SettingsOverriddenTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests that whether cloud settings are overridden.
   *
   * @param string $network_id
   *   Network id.
   * @param string $secret_key
   *   Secret key.
   * @param string $app_uuid
   *   App uuid.
   * @param array $metadata
   *   Metadata.
   * @param bool $is_overridden
   *   Expected result for override.
   *
   * @dataProvider settingsDataProvider
   *
   * @throws \ReflectionException
   */
  public function testOverriddenSettings(string $network_id, string $secret_key, string $app_uuid, array $metadata, bool $is_overridden): void {
    $settings = new Settings(
      $this->prophesize(Config::class)->reveal(),
      $network_id,
      $secret_key,
      $app_uuid,
      $metadata
    );
    $subscription = $this->prophesize(Subscription::class);
    $subscription
      ->getSettings()
      ->willReturn($settings);
    $subscription
      ->getProvider()
      ->willReturn('acquia_cloud');
    $settings_form = new SettingsForm(
      $this->prophesize(ConfigFactoryInterface::class)->reveal(),
      $this->prophesize(ModuleHandlerInterface::class)->reveal(),
      $this->prophesize(PrivateKey::class)->reveal(),
      $subscription->reveal(),
      $this->prophesize(StateInterface::class)->reveal(),
      $this->prophesize(SiteProfile::class)->reveal(),
      $this->prophesize(EventDispatcherInterface::class)->reveal(),
      $this->prophesize(TypedConfigManagerInterface::class)->reveal()
    );
    $method_reflection = new \ReflectionMethod($settings_form, 'isCloudOverridden');
    $method_reflection->setAccessible(TRUE);
    self::assertEquals($is_overridden, $method_reflection->invoke($settings_form));

  }

  /**
   * Data provider for settings object.
   *
   * @return iterable
   *   Data provider.
   */
  public static function settingsDataProvider(): iterable {
    yield [
      'network_id',
      'secret_key',
      'app_uuid',
      [
        'ah_network_identifier' => 'network_id',
        'ah_network_key' => 'secret_key',
        'AH_APPLICATION_UUID' => 'app_uuid',
      ],
      FALSE,
    ];
    yield [
      'updated_network_id',
      'secret_key',
      'app_uuid',
      [
        'ah_network_identifier' => 'network_id',
        'ah_network_key' => 'secret_key',
        'AH_APPLICATION_UUID' => 'app_uuid',
      ],
      TRUE,
    ];
    yield [
      'network_id',
      'updated_secret_key',
      'app_uuid',
      [
        'ah_network_identifier' => 'network_id',
        'ah_network_key' => 'secret_key',
        'AH_APPLICATION_UUID' => 'app_uuid',
      ],
      TRUE,
    ];
    yield [
      'network_id',
      'secret_key',
      'updated_app_uuid',
      [
        'ah_network_identifier' => 'network_id',
        'ah_network_key' => 'secret_key',
        'AH_APPLICATION_UUID' => 'app_uuid',
      ],
      TRUE,
    ];
    yield [
      'updated_network_id',
      'updated_secret_key',
      'updated_app_uuid',
      [
        'ah_network_identifier' => 'network_id',
        'ah_network_key' => 'secret_key',
        'AH_APPLICATION_UUID' => 'app_uuid',
      ],
      TRUE,
    ];
  }

}
