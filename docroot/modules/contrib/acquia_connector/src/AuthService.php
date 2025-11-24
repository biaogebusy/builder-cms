<?php

declare(strict_types=1);

namespace Drupal\acquia_connector;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Site\Settings as CoreSettings;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Auth service for OAuth authentication with Acquia Cloud.
 */
final class AuthService {

  private const PKCE_KEY = 'acquia_connector_pkce_code';

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  private CsrfTokenGenerator $csrfToken;

  /**
   * The HTTP client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  private ClientFactory $clientFactory;

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  private SessionInterface $session;

  /**
   * The factory for expirable key value stores.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  private KeyValueExpirableFactoryInterface $keyValueExpirableFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private TimeInterface $time;

  /**
   * Constructs a new AuthService object.
   *
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   * @param \Drupal\Core\Http\ClientFactory $client_factory
   *   The HTTP client factory.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_expirable_factory
   *   The factory for expirable key value stores.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(CsrfTokenGenerator $csrf_token, ClientFactory $client_factory, SessionInterface $session, KeyValueExpirableFactoryInterface $key_value_expirable_factory, StateInterface $state, TimeInterface $time) {
    $this->csrfToken = $csrf_token;
    $this->clientFactory = $client_factory;
    $this->session = $session;
    $this->keyValueExpirableFactory = $key_value_expirable_factory;
    $this->state = $state;
    $this->time = $time;
  }

  /**
   * Get the authorization URL.
   *
   * @return \Drupal\Core\Url
   *   The URL.
   * @deprecated in acquia_connector:4.1.1 and is removed from
   * acquia_connector:5.0.0.
   *
   * @internal
   */
  public function getAuthUrl(): Url {
    return Url::fromRoute('acquia_connector.setup_oauth');
  }

  /**
   * Verify Client ID Authorization works.
   *
   * @return boolean
   *   The status of the client id against Acquia's IDP.
   *
   * @deprecated in acquia_connector:4.1.1 and is removed from
   * acquia_connector:5.0.0.
   *
   * @internal
   */
  public function authorizeClientId(): bool {
    return FALSE;
  }

  /**
   * Finalizes the OAuth process.
   *
   * @param string $code
   *   The authorization code.
   * @param string $state
   *   The state token.
   *
   * @deprecated in acquia_connector:4.1.1 and is removed from
   * acquia_connector:5.0.0.
   *
   * @internal
   */
  public function finalize(string $code, string $state): void {
    $this->session->remove(self::PKCE_KEY);
  }

  /**
   * Authenticates with the Acquia Cloud using API key & secret.
   */
  public function authenticateWithApi(string $api_key, string $api_secret): array {
    $result = ["success" => TRUE, "message" => ""];
    $client = $this->clientFactory->fromOptions([
      'base_uri' => (new Uri())
        ->withScheme('https')
        ->withHost(self::getIdpHost()),
    ]);
    try {
      $response = $client->post('/api/auth/oauth/token', [
        RequestOptions::FORM_PARAMS => [
          'grant_type' => 'client_credentials',
          'client_id' => $api_key,
          'client_secret' => $api_secret,
        ],
        'headers' => [
          'Content-Type' => 'application/x-www-form-urlencoded',
          'Accept' => 'application/json',
        ],
      ]);
      $access_data = Json::decode((string) $response->getBody());
      $this->keyValueExpirableFactory->get('acquia_connector')->setWithExpire(
        'oauth',
        $access_data,
        $access_data['expires_in']
      );
    }
    catch (RequestException $exception) {
      $response = $exception->getResponse();
      $result["success"] = FALSE;
      $result["message"] = $exception->getMessage();
    }
    $result['response'] = $response;
    return $result;
  }

  /**
   * Refreshes the access token.
   *
   * @deprecated in acquia_connector:4.1.1
   *
   * @internal
   */
  public function refreshAccessToken(): void {
    $this->getAccessToken();
  }
  /**
   * Gets the access token data.
   *
   * @phpstan-return array{access_token: string, expires: int}
   *
   * @return array|null
   *   The access token data, or NULL if not set.
   */
  public function getAccessToken(): ?array {
    $token = $this->keyValueExpirableFactory->get('acquia_connector')->get('oauth');
    if (!$token) {
      // If the access token is missing, attempt to re-authenticate
      $api_credentials = Json::decode($this->state->get('acquia_connector.credentials', ''));
      if (!empty($api_credentials)) {
        $result = $this->authenticateWithApi($api_credentials['api_key'], $api_credentials['api_secret']);
        if ($result["success"] === TRUE) {
          return $this->keyValueExpirableFactory->get('acquia_connector')->get('oauth');
        }
      }
    }
    return $token;
  }

  /**
   * Cron refresh of the access token.
   *
   * @deprecated in acquia_connector:4.1.1
   *
   * @internal
   */
  /**
   * Cron refresh of the access token.
   */
  public function cronRefresh(): void {
    $last_refresh_timestamp = $this->state->get('acquia_connector.oauth_refresh.timestamp', 0);
    if ($this->time->getCurrentTime() - $last_refresh_timestamp > 1800) {
      try {
        $this->getAccessToken();
      }
      catch (RequestException $exception) {
      } finally {
        $this->state->set('acquia_connector.oauth_refresh.timestamp', $this->time->getRequestTime());
      }
    }
  }

  /**
   * Get the identity provider host.
   *
   * @return string
   *   The host.
   */
  private static function getIdpHost(): string {
    return CoreSettings::get('acquia_connector.idp_host', 'accounts.acquia.com');
  }

  /**
   * Resets both oauth and api/secret credentials.
   */
  public function resetCredentials(): void {
    $this->session->remove(self::PKCE_KEY);
    $this->keyValueExpirableFactory->get('acquia_connector')->delete('oauth');
    $this->state->delete('acquia_connector.credentials');
    $this->state->delete('acquia_connector.oauth_refresh.timestamp');
  }

}
