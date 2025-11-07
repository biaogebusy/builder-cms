<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\ClientAuthorization;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationPluginBase;

/**
 * Provides Anonymous authorization.
 *
 * @ClientAuthorization(
 *   id = "anonymous",
 *   label = @Translation("Anonymous"),
 * )
 */
class Anonymous extends ClientAuthorizationPluginBase {

  /**
   * {@inheritdoc}
   */
  public function checkIfAvailable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getClient($url) {
    $options = [
      'base_uri' => $url . '/',
      'cookies' => TRUE,
      'allow_redirects' => TRUE,
    ];

    $credentials = $this->keyService->getCredentials($this);
    if (!empty($credentials['username']) && !empty($credentials['password'])) {
      $options['auth'] = [
        $credentials['username'],
        $credentials['password'],
      ];
    }

    return $this->httpClientFactory->fromOptions($options);
  }

  /**
   * {@inheritdoc}
   */
  public function getJsonApiClient($url) {
    $options = [
      'base_uri' => $url . '/',
      'headers' => [
        'Content-type' => 'application/vnd.api+json',
      ],
    ];

    $credentials = $this->keyService->getCredentials($this);
    if (!empty($credentials['username']) && !empty($credentials['password'])) {
      $options['auth'] = [
        $credentials['username'],
        $credentials['password'],
      ];
    }

    return $this->httpClientFactory->fromOptions($options);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $credentials = $this->keyService->getCredentials($this);

    $form['entity_share']['#description'] = $this->t('Leave empty if the server website is not protected via HTTP Password.');
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
      $form['key']['id']['#description'] = $this->t('Select the key you have configured to hold the HTTP Password credentials.');
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // To prevent validation from parent::validateConfigurationForm() as
    // credentials is not required.
  }

}
