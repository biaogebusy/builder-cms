<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use GuzzleHttp\Exception\ClientException;

/**
 * @coversDefaultClass \Drupal\acquia_connector\AuthService
 * @group acquia_connector
 */
final class AuthServiceTest extends AcquiaConnectorTestBase {

  /**
   * Tests authenticateWithApi.
   *
   * @covers ::authenticateWithApi
   *
   * @dataProvider authenticateWithApiData
   */
  public function testAuthenticateWithApi($expected_result, $api_key, $secret_key): void {
    $sut = $this->container->get('acquia_connector.auth_service');
    $response = $sut->authenticateWithApi($api_key, $secret_key);
    $response_data = Json::decode((string) $response['response']->getBody());

    self::assertEquals($expected_result['response_code'], $response['response']->getStatusCode());
    self::assertEquals($expected_result['success'], $response['success']);
    self::assertEquals($expected_result['message'], $response['message']);
    if ($expected_result['success'] === TRUE) {
      self::assertEquals($expected_result['access_token'], $response_data['access_token']);
      self::assertEquals($expected_result['expires_in'], $response_data['expires_in']);
      self::assertEquals($expected_result['token_type'], $response_data['token_type']);
    }
  }

  /**
   * Test data for ::AuthenticateWithApi.
   */
  public static function authenticateWithApiData() {
    yield 'Success with key/secret' => [
      [
        'success' => TRUE,
        'message' => "",
        'access_token' => 'ACCESS_TOKEN',
        'expires_in' => 300,
        'token_type' => 'bearer',
        'response_code' => 200,
      ],
      'VALID_KEY',
      'VALID_SECRET',
    ];
    yield 'Invalid key/secret' => [
      [
        "success" => FALSE,
        "message" => 'Client error: `POST https://accounts.acquia.com/api/auth/oauth/token` resulted in a `400 Bad Request` response:
{"error":"invalid_client","error_description":"The client credentials are invalid"}
',
        'response_code' => 400],
      'BAD_KEY',
      'BAD_SECRET',
    ];
    yield 'Missing key/secret' => [
      [
        "success" => FALSE,
        "message" => 'Client error: `POST https://accounts.acquia.com/api/auth/oauth/token` resulted in a `400 Bad Request` response:
{"error":"invalid_client","error_description":"client credentials are required"}
',
        'response_code' => 400],
      '',
      '',
    ];
  }

  /**
   * Tests cron refresh of access token.
   *
   * @covers ::cronRefresh
   */
  public function testCron(): void {
    $this->container
      ->get('keyvalue.expirable')
      ->get('acquia_connector')
      ->setWithExpire(
        'oauth',
        [
          'token_type' => 'Bearer',
          'expires_in' => 300,
          'access_token' => 'REFRESH_TOKEN',
          'scope' => 'role:authenticated-user',
        ],
        5400
      );

    $request_timestamp = $this->container->get('datetime.time')->getRequestTime();

    $last_refresh_timestamp = $this->container->get('state')->get('acquia_connector.oauth_refresh.timestamp', 0);
    self::assertEquals(0, $last_refresh_timestamp);
    $sut = $this->container->get('acquia_connector.auth_service');
    $sut->cronRefresh();
    $last_refresh_timestamp = $this->container->get('state')->get('acquia_connector.oauth_refresh.timestamp', 0);
    self::assertEquals($request_timestamp, $last_refresh_timestamp);

    self::assertEquals(
      [
        'token_type' => 'Bearer',
        'expires_in' => 300,
        'access_token' => 'REFRESH_TOKEN',
        'scope' => 'role:authenticated-user',
      ],
      $sut->getAccessToken()
    );
  }

}
