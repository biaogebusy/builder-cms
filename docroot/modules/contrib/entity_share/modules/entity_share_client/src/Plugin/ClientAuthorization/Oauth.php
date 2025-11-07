<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\ClientAuthorization;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationPluginBase;
use Drupal\entity_share_client\Entity\Remote;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Oauth2 based client authorization.
 *
 * The entity share server needs to be configured with an Oauth scope or role
 * with permission entity_share_server_access_channels.
 *
 * @ClientAuthorization(
 *   id = "oauth",
 *   label = @Translation("Oauth2"),
 * )
 */
class Oauth extends ClientAuthorizationPluginBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    /** @var \Drupal\entity_share_client\Plugin\ClientAuthorization\Oauth $instance */
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $instance->messenger = $container->get('messenger');
    $instance->logger = $container->get('logger.channel.entity_share_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function checkIfAvailable() {
    return TRUE;
  }

  /**
   * Obtains the stored or renewed access token based on expiration state.
   *
   * @param string $url
   *   The url to the remote.
   *
   * @return string
   *   The token value.
   */
  protected function getAccessToken($url) {
    $configuration = $this->getConfiguration();
    $accessToken = $this->keyValueStore->get($configuration['uuid'] . '-' . $this->getPluginId());
    $credentials = $this->keyService->getCredentials($this);
    if ($accessToken instanceof AccessTokenInterface) {
      if ($accessToken->hasExpired()) {
        // Get the oauth client.
        $oauth_client = $this->getOauthClient($url, $credentials);
        // Try to get an access token using the authorization code grant.
        try {
          $newAccessToken = $oauth_client->getAccessToken(
            'refresh_token',
            [
              'refresh_token' => $accessToken->getRefreshToken(),
            ]
          );
        }
        catch (\Throwable $e) {
          // If refresh token failed try to get an access token using the
          // client credentials grant.
          $this->logger->critical(
            'Entity Share refresh oauth token request failed with Exception: %exception_type and error: %error.',
            [
              '%exception_type' => get_class($e),
              '%error' => $e->getMessage(),
            ]
          );
          try {
            $newAccessToken = $oauth_client->getAccessToken('client_credentials');
          }
          catch (\Throwable $e) {
            $this->logger->critical(
              'Entity Share new oauth token request failed with Exception: %exception_type and error: %error.',
              [
                '%exception_type' => get_class($e),
                '%error' => $e->getMessage(),
              ]
            );
            return '';
          }
        }
        $this->keyValueStore->set($configuration['uuid'] . '-' . $this->getPluginId(), $newAccessToken);

        return $newAccessToken->getToken();
      }
      return $accessToken->getToken();
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getClient($url) {
    $token = $this->getAccessToken($url);
    return $this->httpClientFactory->fromOptions(
      [
        'base_uri' => $url . '/',
        'allow_redirects' => TRUE,
        'headers' => [
          'Authorization' => 'Bearer ' . $token,
        ],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getJsonApiClient($url) {
    $token = $this->getAccessToken($url);
    return $this->httpClientFactory->fromOptions(
      [
        'base_uri' => $url . '/',
        'headers' => [
          'Content-type' => 'application/vnd.api+json',
          'Authorization' => 'Bearer ' . $token,
        ],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  ) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['entity_share']['#title'] = $this->t(
      'Oauth using <em>Resource owner password credentials</em> grant'
    );
    $form['entity_share']['#description'] = $this->t(
      'A token will be requested and saved in State storage when this form is submitted. The username and password entered here are not saved, but are only used to request the token.'
    );
    $form['entity_share']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
    ];

    $form['entity_share']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
    ];

    $credentials = $this->keyService->getCredentials($this);
    $form['entity_share']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $credentials['client_id'] ?? '',
    ];
    $form['entity_share']['client_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('Client secret'),
    ];
    $form['entity_share']['authorization_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authorization path on the remote website'),
      '#default_value' => $credentials['authorization_path'] ?? '/oauth/authorize',
    ];
    $form['entity_share']['token_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token path on the remote website'),
      '#default_value' => $credentials['token_path'] ?? '/oauth/token',
    ];
    if ($this->keyService->additionalProviders()) {
      $this->expandedProviderOptions($form);
      // Username and password are also required if Key module is involved.
      $form['key']['#description'] = $this->t(
        'A token will be requested and saved in State storage when this form is submitted. The username and password entered here are not saved, but are only used to request the token.'
      );
      $form['key']['username'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
      ];

      $form['key']['password'] = [
        '#type' => 'password',
        '#title' => $this->t('Password'),
      ];
      $form['key']['id']['#key_filters'] = ['type' => 'entity_share_oauth'];
      $form['key']['id']['#description'] = $this->t('Select the key you have configured to hold the Oauth credentials.');
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $configuration = $this->getConfiguration();
    /** @var \Drupal\entity_share_client\Entity\RemoteInterface $remote */
    $remote = $form_state->get('remote');
    $resetConfiguration = $configuration;
    $provider = $values['credential_provider'];
    $credentials = $values[$provider];
    array_walk($credentials, function (&$value) {
      $value = trim($value);
    });
    $key = $configuration['uuid'];
    if ($provider == 'key') {
      $key = $credentials['id'];
    }
    $configuration['data'] = [
      'credential_provider' => $provider,
      'storage_key' => $key,
    ];
    $this->setConfiguration($configuration);
    try {
      // Try to obtain a token.
      switch ($provider) {
        case 'key':
          $requestCredentials = $this->keyService->getCredentials($this);
          $requestCredentials['username'] = $credentials['username'];
          $requestCredentials['password'] = $credentials['password'];
          $accessToken = $this->initializeToken($remote, $requestCredentials);
          // In case the credentials were previously stored locally, clear
          // the local storage.
          $this->keyValueStore->delete($configuration['uuid']);
          break;

        default:
          $accessToken = $this->initializeToken($remote, $credentials);
          // Remove the username and password.
          unset($credentials['username']);
          unset($credentials['password']);
          $this->keyValueStore->set($configuration['uuid'], $credentials);
      }
      // Save the token, using the plugin id appended to the uuid to create a
      // unique key that is distinct from the credential key that may be
      // used for local storage above.
      $this->keyValueStore->set($configuration['uuid'] . '-' . $this->getPluginId(), $accessToken);

      $this->messenger->addStatus(
        $this->t('OAuth token obtained from remote website and stored.')
      );
    }
    catch (IdentityProviderException $e) {
      // Failed to get the access token.
      // Reset original configuration.
      $this->setConfiguration($resetConfiguration);
      $this->messenger->addError(
        $this->t(
          'Unable to obtain an OAuth token. The error message is: @message',
          ['@message' => $e->getMessage()]
        )
      );
    }
  }

  /**
   * Helper function to initialize the OAuth client provider.
   *
   * @param string $url
   *   URL to request.
   * @param array $credentials
   *   Trial credentials.
   *
   * @return \League\OAuth2\Client\Provider\GenericProvider
   *   An OAuth client provider.
   */
  protected function getOauthClient(string $url, array $credentials) {
    $oauth_client = new GenericProvider(
      [
        'clientId' => $credentials['client_id'],
        'clientSecret' => $credentials['client_secret'],
        'urlAuthorize' => $url . $credentials['authorization_path'],
        'urlAccessToken' => $url . $credentials['token_path'],
        'urlResourceOwnerDetails' => '',
      ],
      [
        // Force our own HTTP Client to have overridden settings from
        // setting.php taken into account.
        'httpClient' => $this->httpClientFactory->fromOptions(),
      ]
    );
    return $oauth_client;
  }

  // phpcs:disable
  /**
   * Helper function to initialize a token.
   *
   * @param \Drupal\entity_share_client\Entity\Remote $remote
   *   The remote website for which authorization is needed.
   * @param array $credentials
   *   Trial credentials.
   *
   * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
   *   Exception thrown if the provider response contains errors.
   *
   * @deprecated in 8.x-3.0-beta4 and will be removed in 4.0.0. Use
   *   initializeToken() instead.
   *
   * @SuppressWarnings(PHPMD.ErrorControlOperator)
   * cSpell:disable.
   */
  public function initalizeToken(Remote $remote, array $credentials) {
    // cSpell:enable.
    @trigger_error(__METHOD__ . '() is deprecated in 8.x-3.0-beta4 and will be removed in 4.0.0. use initializeToken() instead.', \E_USER_DEPRECATED);
    $this->initializeToken($remote, $credentials);
  }
  // phpcs:enable

  /**
   * Helper function to initialize a token.
   *
   * @param \Drupal\entity_share_client\Entity\Remote $remote
   *   The remote website for which authorization is needed.
   * @param array $credentials
   *   Trial credentials.
   *
   * @return \League\OAuth2\Client\Token\AccessTokenInterface
   *   A valid access token.
   *
   * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
   *   Exception thrown if the provider response contains errors.
   */
  public function initializeToken(Remote $remote, array $credentials) {
    $oauth_client = $this->getOauthClient($remote->get('url'), $credentials);
    // Try to get an access token using the
    // resource owner password credentials grant.
    return $oauth_client->getAccessToken(
      'password',
      [
        'username' => $credentials['username'],
        'password' => $credentials['password'],
      ]
    );
  }

}
