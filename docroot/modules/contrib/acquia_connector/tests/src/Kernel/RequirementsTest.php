<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel;

use Drupal\acquia_connector\Subscription;
use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * Tests requirements for acquia_connector.
 *
 * @group acquia_connector
 */
final class RequirementsTest extends AcquiaConnectorTestBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    // Fake the installation profile for system_requirements().
    $container->setParameter('install_profile', 'standard');
  }

  /**
   * Test that we never get REQUIREMENT_ERROR for runtime PHP requirement.
   */
  public function testPhpEol(): void {
    // Preload install files for system and acquia_connector to ensure their
    // hooks are discovered. They are not loaded in `drupal_load_updates` due to
    // the fact module schema is not registered in Kernel tests.
    $module_handler = $this->container->get('module_handler');
    $module_handler->loadInclude('system', 'install');
    $module_handler->loadInclude('acquia_connector', 'install');

    $requirements = $this->container->get('system.manager')->listRequirements();

    self::assertArrayHasKey('php', $requirements);
    // Severity is only set if there is an issue with PHP. If it is set, ensure
    // it is not set to REQUIREMENT_ERROR.
    if (isset($requirements['php']['severity'])) {
      self::assertNotEquals(REQUIREMENT_ERROR, $requirements['php']['severity']);
    }
  }

  /**
   * Checks the requirements for acquia connector subscription.
   *
   * @dataProvider subscriptionDataProvider
   */
  public function testSubscriptionRequirements(bool $is_active, bool $has_credentials): void {
    $app_uuid = '1b2c3456-a123-456d-a789-e1234567895d';
    $subscription = $this->createMock(Subscription::class);
    $subscription
      ->method('isActive')
      ->willReturn($is_active);
    $subscription
      ->method('hasCredentials')
      ->willReturn($has_credentials);
    $subscription
      ->method('getSubscription')
      ->willReturn(['uuid' => $app_uuid]);
    $this->container->set('acquia_connector.subscription', $subscription);
    $this->container->get('module_handler')->loadInclude('acquia_connector', 'install');
    $requirements = acquia_connector_requirements('runtime');
    self::assertArrayHasKey('acquia_subscription_status', $requirements);
    $subscription_requirements = $requirements['acquia_subscription_status'];
    self::assertEquals('Acquia Subscription status', $subscription_requirements['title']);
    $description = $subscription_requirements['description']->__toString();
    self::assertStringContainsString('manually refresh the subscription status', $description);
    if ($is_active) {
      self::assertEquals(REQUIREMENT_OK, $subscription_requirements['severity']);
      self::assertEquals('Active', $subscription_requirements['value']);
    }
    elseif (!$has_credentials) {
      self::assertEquals(REQUIREMENT_WARNING, $subscription_requirements['severity']);
      self::assertEquals('Unknown', $subscription_requirements['value']);
      self::assertStringContainsString('You did not complete your signup to Acquia. You can provide the subscription identifier and the subscription key', $description);
    }
    else {
      self::assertEquals(REQUIREMENT_WARNING, $subscription_requirements['severity']);
      self::assertEquals('Inactive', $subscription_requirements['value']);
      self::assertStringContainsString("https://cloud.acquia.com/app/develop/applications/$app_uuid", $description);
      self::assertStringContainsString('Your subscription is expired or you are using an invalid identifier and key pair. You can check the subscription identifier and the subscription key', $description);
    }
  }

  /**
   * Data provider for subscription.
   *
   * @return iterable
   *   Iterable.
   */
  public static function subscriptionDataProvider(): iterable {
    yield [
      TRUE,
      TRUE,
    ];
    yield [
      FALSE,
      FALSE,
    ];
    yield [
      FALSE,
      TRUE,
    ];
  }

}
