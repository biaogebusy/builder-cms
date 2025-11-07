<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\ClientAuthorization;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationPluginBase;

/**
 * Provides Basic Auth based client authorization.
 *
 * @ClientAuthorization(
 *   id = "basic_auth",
 *   label = @Translation("Basic Auth"),
 * )
 */
class BasicAuth extends ClientAuthorizationPluginBase {

  /**
   * {@inheritdoc}
   */
  public function checkIfAvailable() {
    // Basic Auth is a core module which any server can enable.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getClient($url) {
    $credentials = $this->keyService->getCredentials($this);
    $http_client = $this->httpClientFactory->fromOptions([
      'base_uri' => $url . '/',
      'cookies' => TRUE,
      'allow_redirects' => TRUE,
    ]);

    $http_client->post('/user/login', [
      'form_params' => [
        'name' => $credentials['username'],
        'pass' => $credentials['password'],
        'form_id' => 'user_login_form',
      ],
    ]);

    return $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public function getJsonApiClient($url) {
    $credentials = $this->keyService->getCredentials($this);
    return $this->httpClientFactory->fromOptions([
      'base_uri' => $url . '/',
      'auth' => [
        $credentials['username'],
        $credentials['password'],
      ],
      'headers' => [
        'Content-type' => 'application/vnd.api+json',
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $credentials = $this->keyService->getCredentials($this);
    $form['entity_share']['username'] = [
      '#type' => 'textfield',
      '#required' => FALSE,
      '#title' => $this->t('Username'),
      '#default_value' => $credentials['username'] ?? '',
    ];

    $form['entity_share']['password'] = [
      '#type' => 'password',
      '#required' => FALSE,
      '#title' => $this->t('Password'),
      '#default_value' => $credentials['password'] ?? '',
    ];
    if ($this->keyService->additionalProviders()) {
      $this->expandedProviderOptions($form);
      $form['key']['id']['#key_filters'] = ['type' => 'entity_share_basic_auth'];
      $form['key']['id']['#description'] = $this->t('Select the key you have configured to hold the Basic Auth credentials.');
    }

    $form['warning_message'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          $this->t('With the Basic Auth authorization method you need to ensure that the %module_name module is enabled on the server website.', [
            '%module_name' => $this->t('HTTP Basic Authentication'),
          ]),
        ],
      ],
    ];
    return $form;
  }

}
