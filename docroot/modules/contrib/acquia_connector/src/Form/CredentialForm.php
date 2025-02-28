<?php

namespace Drupal\acquia_connector\Form;

use Drupal\acquia_connector\Client;
use Drupal\acquia_connector\ConnectorException;
use Drupal\acquia_connector\Helper\Storage;
use Drupal\acquia_connector\Subscription;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for Acquia Credentials.
 */
class CredentialForm extends ConfigFormBase {

  /**
   * The Acquia client.
   *
   * @var \Drupal\acquia_connector\Client
   */
  protected $client;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\acquia_connector\Client $client
   *   The Acquia client.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Client $client) {
    $this->configFactory = $config_factory;
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('acquia_connector.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['acquia_connector.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_connector_settings_credentials';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $storage = new Storage();
    $form['#prefix'] = $this->t('Enter your product keys from your <a href=":net">application overview</a> or <a href=":url">log in</a> to connect your site to Acquia Insight.', [
      ':net' => Url::fromUri('https://cloud.acquia.com')->getUri(),
      ':url' => Url::fromRoute('acquia_connector.setup')->toString(),
    ]);

    $form['acquia_identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Identifier'),
      '#default_value' => $storage->getIdentifier(),
      '#required' => TRUE,
    ];
    $form['acquia_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Network key'),
      '#default_value' => $storage->getKey(),
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Connect'),
    ];
    $form['actions']['signup'] = [
      '#markup' => $this->t('Need a subscription? <a href=":url">Get one</a>.', [
        ':url' => Url::fromUri('https://www.acquia.com/acquia-cloud-free')->getUri(),
      ]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    try {
      $response = $this->client->nspiCall(
        '/agent-api/subscription',
        ['identifier' => trim($form_state->getValue('acquia_identifier'))],
        trim($form_state->getValue('acquia_key')));
    }
    catch (ConnectorException $e) {
      // Set form error to prevent switching to the next page.
      if ($e->isCustomized()) {
        // Allow to connect with expired subscription.
        if ($e->getCustomMessage('code') == Subscription::EXPIRED) {
          $form_state->setValue('subscription', 'Expired subscription.');
          return;
        }
        acquia_connector_report_restapi_error($e->getCustomMessage('code'), $e->getCustomMessage());
        $form_state->setErrorByName('');
      }
      else {
        $form_state->setErrorByName('', $this->t('Server error, please submit again.'));
      }
      return;
    }

    $response = $response['result'];

    if (empty($response['body']['subscription_name'])) {
      $form_state->setErrorByName('acquia_identifier', $this->t('No subscriptions were found.'));
    }
    else {
      $form_state->setValue('subscription', $response['body']['subscription_name']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('acquia_connector.settings');

    $config->set('subscription_name', $form_state->getValue('subscription'))
      ->save();

    $storage = new Storage();
    $storage->setKey($form_state->getValue('acquia_key'));
    $storage->setIdentifier($form_state->getValue('acquia_identifier'));

    // Check subscription and send a heartbeat to Acquia via XML-RPC.
    // Our status gets updated locally via the return data.
    $subscription = new Subscription();
    $subscription_data = $subscription->update();

    // Redirect to the path without the suffix.
    $form_state->setRedirect('acquia_connector.settings');

    drupal_flush_all_caches();

    if ($subscription->isActive()) {
      $this->messenger()->addStatus($this->t('<h3>Connection successful!</h3>You are now connected to Acquia Cloud. Please enter a name for your site to begin sending profile data.'));
    }
  }

}
