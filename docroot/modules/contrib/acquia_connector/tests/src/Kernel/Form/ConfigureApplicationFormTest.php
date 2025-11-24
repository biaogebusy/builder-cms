<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel\Form;

use Drupal\Core\Site\Settings as CoreSettings;
use Drupal\Core\Url;
use Drupal\Tests\acquia_connector\Kernel\AcquiaConnectorTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests configure application form.
 *
 * @coversDefaultClass \Drupal\acquia_connector\Form\ConfigureApplicationForm
 * @group acquia_connector
 */
final class ConfigureApplicationFormTest extends AcquiaConnectorTestBase {

  /**
   * Test the behavior when there is authentication is missing.
   *
   * This method tests the behavior of a specific scenario where
   * no authentication is provided.
   */
  public function testWithNoAuth(): void {
    $this->createUserWithSession();

    $request = Request::create(
      Url::fromRoute('acquia_connector.setup_configure')->toString()
    );
    $response = $this->doRequest($request);
    self::assertEquals(302, $response->getStatusCode());
    self::assertEquals(
      Url::fromRoute('acquia_connector.setup_oauth')->toString(),
      $response->headers->get('Location')
    );
    $error_msg = $this->container->get('messenger')->messagesByType('error');
    self::assertEquals(
      'We could not retrieve account data, please re-authorize with your Acquia Cloud account. For more information check <a target="_blank" href="https://docs.acquia.com/cloud-platform/known-issues/#unable-to-log-in-through-acquia-connector">this link</a>.',
      (string) array_shift($error_msg)
    );
  }

  /**
   * Tests that api key and secret gets automatically created with auth.
   */
  public function testAutomaticApiKeyCreation(): void {
    $this->createUserWithSession();
    $this->setAccessToken('ACCESS_TOKEN_MULTIPLE_APPLICATIONS');

    $request = Request::create(
      Url::fromRoute('acquia_connector.setup_configure')->toString()
    );
    $response = $this->doRequest($request);
    self::assertEquals(200, $response->getStatusCode());
    // Ensure keys are being stored locally.
    $state = $this->container->get('state');
    $this->assertEquals('{"api_key":"VALID_KEY","api_secret":"VALID_SECRET","client_id":"137bd484-dcc8-4950-a784-1f01de7f6378","client_secret":"4DmbUmGiUkafdjcZk2yV6u17jPmmunwt8\/47mKdAQIc=","_links":{"self":{"href":"https:\/\/cloud.acquia.com\/api\/account\/tokens"},"parent":{"href":"https:\/\/cloud.acquia.com\/api\/account"},"notification":{"href":"https:\/\/cloud.acquia.com\/api\/notifications\/ab142771-826e-42b0-a53c-e112b70448d2"}}}', $state->get('acquia_connector.credentials', ''));
  }
  /**
   * Test when there is an error fetching the application keys.
   */
  public function testWithErrorGettingApplicationKeys(): void {
    $this->createUserWithSession();

    $this->setAccessToken('ACCESS_TOKEN_ERROR_GETTING_APPLICATION_KEYS');

    $request = Request::create(
      Url::fromRoute('acquia_connector.setup_configure')->toString()
    );
    $response = $this->doRequest($request);
    self::assertEquals(303, $response->getStatusCode());
    self::assertEquals(
      Url::fromRoute('acquia_connector.setup_oauth')->setAbsolute()->toString(),
      $response->headers->get('Location')
    );
    self::assertEquals(
      ['We encountered an error when retrieving information for the selected application. Try logging into Acquia Cloud again.'],
      $this->container->get('messenger')->messagesByType('error')
    );
  }

  /**
   * Test when the subscription returns no applications.
   */
  public function testWithNoApplications(): void {
    $this->createUserWithSession();

    $this->setAccessToken('ACCESS_TOKEN_NO_APPLICATIONS');

    $request = Request::create(
      Url::fromRoute('acquia_connector.setup_configure')->toString()
    );
    $response = $this->doRequest($request);
    self::assertEquals(302, $response->getStatusCode());
    self::assertEquals(
      Url::fromRoute('acquia_connector.setup_oauth')->toString(),
      $response->headers->get('Location')
    );
    self::assertEquals(
      ['No subscriptions were found for your account.'],
      $this->container->get('messenger')->messagesByType('error')
    );
  }

  /**
   * Tests when the current application uuid doesn't match the subscription.
   */
  public function testApplicationCloudMismatch(): void {
    $this->setAccessToken('ACCESS_TOKEN_ONE_APPLICATION');

    // Emulate Acquia Cloud -- Hardcode the uuid to ensure it is a mismatch.
    $uuid = 'a6ce3b66-febf-487f-8d35-2802c1964a55';
    $this->putEnv('AH_SITE_ENVIRONMENT', 'test');
    $this->putEnv('AH_SITE_NAME', 'foo');
    $this->putEnv('AH_SITE_GROUP', 'bar');
    $this->putEnv('AH_APPLICATION_UUID', $uuid);
    $settings = CoreSettings::getAll();
    $settings['ah_network_identifier'] = 'WRNG-12345';
    $settings['ah_network_key'] = 'TEST_KEY';
    new CoreSettings($settings);

    // User session must be made AFTER we setup cloud environment variables.
    $this->createUserWithSession();

    $request = Request::create(
      Url::fromRoute('acquia_connector.setup_configure')->toString()
    );
    $response = $this->doRequest($request);
    self::assertEquals(302, $response->getStatusCode());
    self::assertEquals(
      Url::fromRoute('acquia_connector.setup_oauth')->toString(),
      $response->headers->get('Location')
    );
    self::assertEquals(
      ['Unable to set Subscription: User does not have access to this application.'],
      $this->container->get('messenger')->messagesByType('error')
    );
  }

  /**
   * Test when a subscription only has one application.
   */
  public function testWithOneApplication(): void {
    $this->createUserWithSession();

    $this->setAccessToken('ACCESS_TOKEN_ONE_APPLICATION');

    $request = Request::create(
      Url::fromRoute('acquia_connector.setup_configure')->toString()
    );
    $response = $this->doRequest($request);
    self::assertEquals(303, $response->getStatusCode());
    self::assertEquals(
      Url::fromRoute('acquia_connector.settings')->setAbsolute()->toString(),
      $response->headers->get('Location')
    );
    $actual_msg = $this->container->get('messenger')->all()['status'];
    self::assertSame(
      ['status' => '<h3>Connection successful!</h3>You are now connected to Acquia Cloud.'],
      ['status' => (string) array_shift($actual_msg)]
    );

    self::assertEquals(
      [
        'active' => TRUE,
        'href' => '',
        'uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
        'subscription_name' => 'Sample subscription',
        'expiration_date' => [
          'value' => '2030-05-12T00:00:00',
        ],
        'product' => [
          'view' => 'Acquia Network',
        ],
        'search_service_enabled' => 1,
        'gratis' => FALSE,
        'application' => [
          'id' => 1234,
          'uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
          'name' => 'Sample application 1',
          'subscription' => [
            'uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d470',
            'name' => 'Sample subscription',
          ],
        ],
      ],
      $this->container->get('acquia_connector.subscription')->getSubscription()
    );
  }

  /**
   * Test when a subscription contains multiple applications.
   */
  public function testWithMultipleApplications(): void {
    $this->createUserWithSession();

    $this->setAccessToken('ACCESS_TOKEN_MULTIPLE_APPLICATIONS');

    $request = Request::create(
      Url::fromRoute('acquia_connector.setup_configure')->toString()
    );
    $response = $this->doRequest($request);
    self::assertEquals(200, $response->getStatusCode());
    $request = Request::create(
      Url::fromRoute('acquia_connector.setup_configure')->toString(),
      'POST',
      [
        'application' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
        // @phpstan-ignore-next-line
        'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
        // @phpstan-ignore-next-line
        'form_token' => (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0],
        // @phpstan-ignore-next-line
        'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
        'op' => 'Save',
      ]);
    $response = $this->doRequest($request);
    self::assertEquals(303, $response->getStatusCode(), $this->content);
    self::assertEquals(
      Url::fromRoute('acquia_connector.settings')->setAbsolute()->toString(),
      $response->headers->get('Location')
    );
    $actual_msg = $this->container->get('messenger')->all()['status'];
    self::assertSame(
      ['status' => '<h3>Connection successful!</h3>You are now connected to Acquia Cloud.'],
      ['status' => (string) array_shift($actual_msg)]
    );

    self::assertEquals(
      [
        'active' => TRUE,
        'href' => '',
        'uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
        'subscription_name' => 'Sample subscription',
        'expiration_date' => [
          'value' => '2030-05-12T00:00:00',
        ],
        'product' => [
          'view' => 'Acquia Network',
        ],
        'search_service_enabled' => 1,
        'gratis' => FALSE,
        'application' => [
          'id' => 1234,
          'uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
          'name' => 'Sample application 1',
          'subscription' => [
            'uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d470',
            'name' => 'Sample subscription',
          ],
        ],
      ],
      $this->container->get('acquia_connector.subscription')->getSubscription()
    );
  }

  /**
   * Sets the access token.
   *
   * @param string $token
   *   The token.
   */
  private function setAccessToken(string $token): void {
    $this->container
      ->get('keyvalue.expirable')
      ->get('acquia_connector')
      ->setWithExpire(
        'oauth',
        [
          'access_token' => $token,
          'refresh_token' => 'REFRESH_TOKEN',
        ],
        5400
      );
  }

}
