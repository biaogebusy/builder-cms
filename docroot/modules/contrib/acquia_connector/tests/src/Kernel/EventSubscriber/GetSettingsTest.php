<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel\EventSubscriber;

use Drupal\acquia_connector\AcquiaConnectorEvents;
use Drupal\acquia_connector\Event\AcquiaSubscriptionSettingsEvent;
use Drupal\Component\Uuid\Php as PhpUuid;
use Drupal\Core\Site\Settings as CoreSettings;
use Drupal\Tests\acquia_connector\Kernel\AcquiaConnectorTestBase;

/**
 * Tests the event subscribers for AcquiaConnectorEvents::GET_SETTINGS.
 *
 * @coversClass \Drupal\acquia_connector\EventSubscriber\GetSettings\FromAcquiaCloud
 * @coversClass \Drupal\acquia_connector\EventSubscriber\GetSettings\FromCoreSettings
 * @coversClass \Drupal\acquia_connector\EventSubscriber\GetSettings\FromCoreState
 *
 * @group acquia_connector
 */
final class GetSettingsTest extends AcquiaConnectorTestBase {

  /**
   * Tests when there are no settings.
   */
  public function testNoSettings(): void {
    $event = $this->dispatchEvent();
    self::assertEquals('core_state', $event->getProvider());
    self::assertEquals('', $event->getSettings()->getIdentifier());
    self::assertEquals('', $event->getSettings()->getSecretKey());
    self::assertFalse($event->getSettings()->isReadonly());
    self::assertEquals('', $event->getSettings()->getApplicationUuid());
    self::assertEquals([], $event->getSettings()->getMetadata());
  }

  /**
   * Tests settings from state storage.
   */
  public function testFromCoreState(): void {
    $uuid = (new PhpUuid())->generate();
    $this->container->get('state')->setMultiple([
      'acquia_connector.identifier' => 'ABC-1234',
      'acquia_connector.key' => 'TEST_KEY',
      'acquia_connector.application_uuid' => $uuid,
    ]);
    $event = $this->dispatchEvent();
    self::assertEquals('core_state', $event->getProvider());
    self::assertEquals('ABC-1234', $event->getSettings()->getIdentifier());
    self::assertEquals('TEST_KEY', $event->getSettings()->getSecretKey());
    self::assertFalse($event->getSettings()->isReadonly());
    self::assertEquals($uuid, $event->getSettings()->getApplicationUuid());
    self::assertEquals([], $event->getSettings()->getMetadata());
  }

  /**
   * Tests from settings override.
   */
  public function testFromCoreSettings(): void {
    $uuid = (new PhpUuid())->generate();
    $settings = CoreSettings::getAll();
    $settings['ah_network_identifier'] = 'ABC-1234';
    $settings['ah_network_key'] = 'TEST_KEY';
    $settings['ah_application_uuid'] = $uuid;
    new CoreSettings($settings);
    $event = $this->dispatchEvent();
    self::assertEquals('core_settings', $event->getProvider());
    self::assertEquals('ABC-1234', $event->getSettings()->getIdentifier());
    self::assertEquals('TEST_KEY', $event->getSettings()->getSecretKey());
    self::assertTrue($event->getSettings()->isReadonly());
    self::assertEquals($uuid, $event->getSettings()->getApplicationUuid());
    self::assertEquals([], $event->getSettings()->getMetadata());
  }

  /**
   * Tests with environment variables from AH.
   */
  public function testFromAcquiaCloud(): void {
    $uuid = (new PhpUuid())->generate();
    $this->putEnv('AH_SITE_ENVIRONMENT', 'test');
    $this->putEnv('AH_SITE_NAME', 'foo');
    $this->putEnv('AH_SITE_GROUP', 'bar');
    $this->putEnv('AH_APPLICATION_UUID', $uuid);

    $settings = CoreSettings::getAll();
    $settings['ah_network_identifier'] = 'ABC-1234';
    $settings['ah_network_key'] = 'TEST_KEY';
    new CoreSettings($settings);

    $event = $this->dispatchEvent();
    self::assertEquals('acquia_cloud', $event->getProvider());
    self::assertEquals('ABC-1234', $event->getSettings()->getIdentifier());
    self::assertEquals('TEST_KEY', $event->getSettings()->getSecretKey());
    self::assertTrue($event->getSettings()->isReadonly());
    self::assertEquals($uuid, $event->getSettings()->getApplicationUuid());
    self::assertEquals([
      'AH_SITE_ENVIRONMENT' => 'test',
      'AH_SITE_NAME' => 'foo',
      'AH_SITE_GROUP' => 'bar',
      'AH_APPLICATION_UUID' => $uuid,
      'ah_network_identifier' => 'ABC-1234',
      'ah_network_key' => 'TEST_KEY',
    ], $event->getSettings()->getMetadata());
  }

  /**
   * Tests with environment variables from Cloud IDE.
   */
  public function testFromAcquiaCloudIde(): void {
    $uuid = (new PhpUuid())->generate();
    $this->putEnv('AH_SITE_ENVIRONMENT', 'ide');
    $this->putEnv('AH_SITE_NAME', 'foo');
    $this->putEnv('AH_SITE_GROUP', 'bar');
    $this->putEnv('AH_APPLICATION_UUID', $uuid);

    $event = $this->dispatchEvent();
    self::assertEquals('core_state', $event->getProvider());
    self::assertEquals('', $event->getSettings()->getIdentifier());
    self::assertEquals('', $event->getSettings()->getSecretKey());
    self::assertFalse($event->getSettings()->isReadonly());
    self::assertEquals('', $event->getSettings()->getApplicationUuid());
    self::assertEquals([], $event->getSettings()->getMetadata());
  }

  /**
   * Tests with environment variables from on-demand environment.
   */
  public function testFromAcquiaCloudOde(): void {
    $uuid = (new PhpUuid())->generate();
    $this->putEnv('AH_SITE_ENVIRONMENT', 'ode');
    $this->putEnv('AH_SITE_NAME', 'foo');
    $this->putEnv('AH_SITE_GROUP', 'bar');
    $this->putEnv('AH_APPLICATION_UUID', $uuid);

    $event = $this->dispatchEvent();
    self::assertEquals('core_state', $event->getProvider());
    self::assertEquals('', $event->getSettings()->getIdentifier());
    self::assertEquals('', $event->getSettings()->getSecretKey());
    self::assertFalse($event->getSettings()->isReadonly());
    self::assertEquals('', $event->getSettings()->getApplicationUuid());
    self::assertEquals([], $event->getSettings()->getMetadata());
  }

  /**
   * Tests with combination of values.
   */
  public function testWithCombination(): void {
    $uuid = '2847ba56-cb57-4d37-85f1-baa69ff0c604';
    $settings = CoreSettings::getAll();
    $settings['ah_network_identifier'] = 'ABC-1234';
    $settings['ah_network_key'] = 'TEST_KEY';
    $settings['ah_application_uuid'] = $uuid;
    new CoreSettings($settings);

    $this->putEnv('AH_SITE_ENVIRONMENT', 'test');
    $this->putEnv('AH_SITE_NAME', 'foo');
    $this->putEnv('AH_SITE_GROUP', 'bar');
    $this->putEnv('AH_APPLICATION_UUID', $uuid);

    $this->container->get('state')->setMultiple([
      'acquia_connector.identifier' => 'ABC-1234',
      'acquia_connector.key' => 'TEST_KEY',
    ]);

    $event = $this->dispatchEvent();
    self::assertEquals('acquia_cloud', $event->getProvider());
    self::assertEquals('ABC-1234', $event->getSettings()->getIdentifier());
    self::assertEquals('TEST_KEY', $event->getSettings()->getSecretKey());
    self::assertTrue($event->getSettings()->isReadonly());
    self::assertEquals([
      'AH_SITE_ENVIRONMENT' => 'test',
      'AH_SITE_NAME' => 'foo',
      'AH_SITE_GROUP' => 'bar',
      'AH_APPLICATION_UUID' => $uuid,
      'ah_network_identifier' => 'ABC-1234',
      'ah_network_key' => 'TEST_KEY',
    ], $event->getSettings()->getMetadata());
  }

  /**
   * Dispatches a settings event.
   *
   * @return \Drupal\acquia_connector\Event\AcquiaSubscriptionSettingsEvent
   *   The dispatched event.
   */
  private function dispatchEvent(): AcquiaSubscriptionSettingsEvent {
    $event = new AcquiaSubscriptionSettingsEvent($this->container->get('config.factory'));
    $this->container->get('event_dispatcher')->dispatch($event, AcquiaConnectorEvents::GET_SETTINGS);
    return $event;
  }

}
