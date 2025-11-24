<?php

namespace Drupal\elasticsearch_connector\Plugin\ElasticSearch\Connector;

use Drupal\Core\Form\FormStateInterface;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

/**
 * Provides an ElasticSearch connector with basic auth.
 *
 * @ElasticSearchConnector(
 *   id = "basicauth",
 *   label = @Translation("HTTP Basic Authentication"),
 *   description = @Translation("ElasticSearch connector with HTTP Basic Auth.")
 * )
 */
class BasicAuthConnector extends StandardConnector {

  /**
   * {@inheritdoc}
   */
  public function getClient(): Client {
    // We only support one host.
    return ClientBuilder::create()
      ->setHosts([$this->configuration['url']])
      ->setBasicAuthentication($this->configuration['username'], $this->configuration['password'])
      ->build();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'username' => '',
      'password' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $this->configuration['username'] ?? '',
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t('If this field is left blank and the HTTP username is filled out, the current password will not be changed.'),
    ];

    $form_state->set('previous_password', $this->configuration['password']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    foreach ($values as $key => $value) {
      // For password fields, there is no default value, they're empty by
      // default. Therefore, we ignore empty submissions if the user didn't
      // change either.
      if ('password' === $key && '' === $value
        && isset($this->configuration['username'])
        && $values['username'] === $this->configuration['username']
      ) {
        $value = $form_state->get('previous_password');
      }

      $form_state->setValue($key, $value);
    }

    // Clean-up the form to avoid redundant entries in the stored configuration.
    $form_state->unsetValue('auth');

    $this->configuration['username'] = $form_state->getValue('username');
    $this->configuration['password'] = $form_state->getValue('password');

    parent::submitConfigurationForm($form, $form_state);
  }

}
