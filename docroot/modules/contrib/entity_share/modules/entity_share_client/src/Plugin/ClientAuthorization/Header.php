<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\ClientAuthorization;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationPluginBase;

/**
 * Provides Header based client authorization.
 *
 * @ClientAuthorization(
 *   id = "header",
 *   label = @Translation("Header"),
 * )
 */
class Header extends ClientAuthorizationPluginBase {

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
    $credentials = $this->keyService->getCredentials($this);
    return $this->httpClientFactory->fromOptions([
      'base_uri' => $url . '/',
      'cookies' => TRUE,
      'allow_redirects' => TRUE,
      'headers' => [
        $credentials['header_name'] => $credentials['header_value'],
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getJsonApiClient($url) {
    $credentials = $this->keyService->getCredentials($this);
    return $this->httpClientFactory->fromOptions([
      'base_uri' => $url . '/',
      'headers' => [
        'Content-type' => 'application/vnd.api+json',
        $credentials['header_name'] => $credentials['header_value'],
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $credentials = $this->keyService->getCredentials($this);

    $form['entity_share']['header_name'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Name'),
      '#default_value' => $credentials['header_name'] ?? '',
    ];

    $form['entity_share']['header_value'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Value'),
      '#default_value' => $credentials['header_value'] ?? '',
    ];
    if ($this->keyService->additionalProviders()) {
      $this->expandedProviderOptions($form);
      $form['key']['id']['#key_filters'] = ['type' => 'entity_share_header'];
      $form['key']['id']['#description'] = $this->t('Select the key you have configured to hold the Header data.');
    }

    return $form;
  }

}
