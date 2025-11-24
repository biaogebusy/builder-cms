<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel\Form;

use Drupal\acquia_connector\AcquiaConnectorEvents;
use Drupal\acquia_connector\Event\AcquiaProductSettingsEvent;
use Drupal\acquia_connector\Event\AcquiaSubscriptionDataEvent;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Url;
use Drupal\Tests\acquia_connector\Kernel\AcquiaConnectorTestBase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\acquia_connector\Form\SettingsForm
 * @group acquia_connector
 */
final class SettingsFormTest extends AcquiaConnectorTestBase implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createUserWithSession();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    $container
      ->register('testing.acquia_connector_subscriber', self::class)
      ->addTag('event_subscriber');
    $container->set('testing.acquia_connector_subscriber', $this);

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      AcquiaConnectorEvents::ACQUIA_PRODUCT_SETTINGS => 'onProductSettings',
      AcquiaConnectorEvents::ALTER_PRODUCT_SETTINGS_SUBMIT => 'onAlterProductSettingsSubmit',
      AcquiaConnectorEvents::GET_SUBSCRIPTION => 'onGetSubscription',
    ];
  }

  /**
   * Event handler for ACQUIA_PRODUCT_SETTINGS.
   *
   * @param \Drupal\acquia_connector\Event\AcquiaProductSettingsEvent $event
   *   The event.
   */
  public function onProductSettings(AcquiaProductSettingsEvent $event) {
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => 'API Key',
      '#default_value' => '',
      '#required' => TRUE,
    ];
    $form['api_url'] = [
      '#type' => 'hidden',
      '#title' => 'API URL',
      '#default_value' => '',
    ];
    $event->setProductSettings(
      'Acquia Example Product',
      'acquia_example_product',
      $form
    );
  }

  /**
   * Event handler for ALTER_PRODUCT_SETTINGS_SUBMIT.
   *
   * @param \Drupal\acquia_connector\Event\AcquiaProductSettingsEvent $event
   *   The event.
   */
  public function onAlterProductSettingsSubmit(AcquiaProductSettingsEvent $event) {
    $form_state = $event->getFormState();
    $form_state['product_settings']['acquia_example_product']['settings']['api_url'] = 'https://example.acquia.com';
    $event->alterProductSettingsSubmit($form_state);
  }

  /**
   * Event handler for GET_SUBSCRIPTION.
   *
   * @param \Drupal\acquia_connector\Event\AcquiaSubscriptionDataEvent $event
   *   The event.
   */
  public function onGetSubscription(AcquiaSubscriptionDataEvent $event) {
    $config = $event->getConfig('acquia_connector.settings');
    $data = $config->get('third_party_settings.acquia_example_product');

    $subscription_data = $event->getData();
    $subscription_data['acquia_example_product'] = $data;
    $event->setData($subscription_data);
  }

  /**
   * Tests redirect from settings to auth if no subscription data.
   */
  public function testRedirectIfNoActiveSubscription(): void {
    $request = Request::create(
      Url::fromRoute('acquia_connector.settings')->toString()
    );
    $response = $this->doRequest($request);
    self::assertEquals(302, $response->getStatusCode());
    self::assertEquals(
      Url::fromRoute('acquia_connector.setup_oauth')->toString(),
      $response->headers->get('Location')
    );
  }

  /**
   * Tests the site name generated if Acquia Cloud hosting.
   */
  public function testSiteNameGeneratedOnAcquiaCloud(): void {
    $this->container->get('state')->setMultiple([
      'acquia_connector.identifier' => 'ABC',
      'acquia_connector.key' => 'DEF',
      'acquia_connector.application_uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
      'spi.site_name' => 'old_stored_site_name',
    ]);
    $this->container->get('acquia_connector.subscription')->populateSettings();

    $request = Request::create(
      Url::fromRoute('acquia_connector.settings')->toString(),
      'GET',
      [],
      [],
      [],
      [
        'AH_SITE_ENVIRONMENT' => 'test',
        'AH_SITE_NAME' => 'foobar',
      ]
    );
    $response = $this->doRequest($request);
    self::assertEquals(200, $response->getStatusCode());
    $this->assertRaw('<h3>Connected to Acquia</h3>');
    $site_name = (string) $this->cssSelect('input[name="name"]')[0]->attributes()->value[0];
    self::assertStringContainsString('0e02b2c3d470: test', $site_name);
  }

  /**
   * Tests the form with subscription data available.
   */
  public function testWithSubscriptionData(): void {
    $this->container->get('state')->setMultiple([
      'acquia_connector.identifier' => 'ABC',
      'acquia_connector.key' => 'DEF',
      'acquia_connector.application_uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
      'spi.site_name' => 'old_stored_site_name',
    ]);
    $this->container->get('acquia_connector.subscription')->populateSettings();

    $request = Request::create(
      Url::fromRoute('acquia_connector.settings')->toString()
    );
    $response = $this->doRequest($request);
    self::assertEquals(200, $response->getStatusCode());
    $this->assertRaw('<h3>Connected to Acquia</h3>');
    $site_name = (string) $this->cssSelect('input[name="name"]')[0]->attributes()->value[0];
    self::assertStringContainsString('0e02b2c3d470: localhost_', $site_name);
    $machine_name = (string) $this->cssSelect('input[name="machine_name"]')[0]->attributes()->value[0];
    self::assertStringContainsString('0e02b2c3d470__localhost_', $machine_name);
    self::assertCount(1, $this->cssSelect('input[name="product_settings[acquia_example_product][settings][api_key]"]'), var_export($this->getRawContent(), TRUE));

    $request = Request::create(
      Url::fromRoute('acquia_connector.settings')->toString(),
      'POST',
      [
        'name' => $site_name,
        'machine_name' => $machine_name,
        'product_settings' => [
          'acquia_example_product' => [
            'settings' => [
              'api_key' => 'ABC123',
            ],
          ],
        ],
        // @phpstan-ignore-next-line
        'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
        // @phpstan-ignore-next-line
        'form_token' => (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0],
        // @phpstan-ignore-next-line
        'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
        'op' => 'Connect',
      ]);
    $response = $this->doRequest($request);
    self::assertEquals(303, $response->getStatusCode(), var_export($response->getContent(), TRUE));
    self::assertEquals(
      ['status' => ['The configuration options have been saved.']],
      $this->container->get('messenger')->all()
    );
    $config = $this->config('acquia_connector.settings')->get('third_party_settings');
    self::assertEquals([
      'acquia_example_product' => [
        'api_key' => 'ABC123',
        'api_url' => 'https://example.acquia.com',
      ],
    ], $config);

    self::assertEquals([
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
      'acquia_example_product' => [
        'api_key' => 'ABC123',
        'api_url' => 'https://example.acquia.com',
      ],
    ], $this->container->get('acquia_connector.subscription')->getSubscription());
  }

}
