<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel;

/**
 * Test integration of connector with the Drupal Toolbar.
 *
 * @group acquia_connector
 */
final class ToolbarIntegrationTest extends AcquiaConnectorTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'toolbar',
  ];

  /**
   * Tests permission is required to view the toolbar.
   */
  public function testWithoutPermission(): void {
    $user = $this->createUser();
    self::assertNotFalse($user);
    $this->container->get('current_user')->setAccount($user);
    self::assertEquals([], acquia_connector_toolbar());
  }

  /**
   * Tests the toolbar output with subscription credentials.
   *
   * @param string $identifier
   *   The network identifier.
   * @param string $key
   *   The network key.
   * @param string $application_uuid
   *   The application UUID.
   * @param string $expected_title
   *   The expected toolbar item title.
   * @param string $expected_url
   *   The expected toolbar item URL.
   *
   * @dataProvider credentialData
   */
  public function testWithoutSubscription(string $identifier, string $key, string $application_uuid, string $expected_title, string $expected_url): void {
    $this->container->get('state')->setMultiple([
      'acquia_connector.identifier' => $identifier,
      'acquia_connector.key' => $key,
      'acquia_connector.application_uuid' => $application_uuid,
    ]);

    $user = $this->createUser(['view acquia connector toolbar']);
    self::assertNotFalse($user);
    $this->container->get('current_user')->setAccount($user);
    $toolbar = acquia_connector_toolbar();
    self::assertArrayHasKey('acquia_connector', $toolbar);
    self::assertEquals(
      ['tags' => ['acquia_connector_subscription']],
      $toolbar['acquia_connector']['#cache']
    );
    self::assertArrayHasKey('tab', $toolbar['acquia_connector']);
    $tab = $toolbar['acquia_connector']['tab'];
    self::assertEquals($expected_title, (string) $tab['#title']);
    self::assertEquals($expected_url, $tab['#url']->toString());
  }

  /**
   * The test data.
   *
   * @return \Generator
   *   The data.
   */
  public static function credentialData() {
    yield 'no credentials' => [
      '',
      '',
      '',
      'Subscription not active',
      '/admin/config/services/acquia-connector/login',
    ];
    yield 'with credentials' => [
      'ABC',
      'DEF',
      'a47ac10b-58cc-4372-a567-0e02b2c3d470',
      'Subscription active',
      'https://cloud.acquia.com/app/develop/applications/a47ac10b-58cc-4372-a567-0e02b2c3d470',
    ];
  }

}
