<?php

namespace Drupal\acquia_connector\Form;

use Drupal\acquia_connector\Client\ClientFactory;
use Drupal\acquia_connector\Subscription;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for Acquia Credentials.
 */
class CredentialForm extends FormBase {

  /**
   * The Acquia client.
   *
   * @var \Drupal\acquia_connector\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * Drupal State Service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Acquia Connector Subscription service.
   *
   * @var \Drupal\acquia_connector\Subscription
   */
  protected $subscription;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\acquia_connector\Client\ClientFactory $client_factory
   *   The Acquia client.
   * @param \Drupal\Core\State\StateInterface $state
   *   Drupal State Service.
   * @param \Drupal\acquia_connector\Subscription $subscription
   *   Acquia Connector Subscription service.
   */
  public function __construct(ClientFactory $client_factory, StateInterface $state, Subscription $subscription) {
    $this->clientFactory = $client_factory;
    $this->state = $state;
    $this->subscription = $subscription;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_connector.client.factory'),
      $container->get('state'),
      $container->get('acquia_connector.subscription')
    );
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

    $form['#prefix'] = $this->t('Enter your product keys from your <a href=":net">application overview</a> or <a href=":url">log in</a> to connect your site to Acquia Cloud.', [
      ':net' => Url::fromUri('https://cloud.acquia.com')->getUri(),
      ':url' => Url::fromRoute('acquia_connector.setup_oauth')->toString(),
    ]);

    $form['identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Identifier'),
      '#default_value' => '',
      '#required' => TRUE,
    ];
    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Network key'),
      '#default_value' => '',
      '#required' => TRUE,
    ];
    $form['application_uuid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application UUID'),
      '#default_value' => '',
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save Credentials to state, only if a new value was added.
    $values = ['identifier', 'key', 'application_uuid'];
    foreach ($values as $value) {
      $this->state->set('acquia_connector.' . $value, $form_state->getValue($value));
    }

    // Our status gets updated locally via the return data.
    // Don't use dependency injection here because we just created the sub.
    // phpcs:ignore
    $subscription = \Drupal::service('acquia_connector.subscription');
    $subscription->populateSettings();

    // Redirect to the path without the suffix.
    $form_state->setRedirect('acquia_connector.settings');

    if ($subscription->isActive()) {
      $this->messenger()->addStatus($this->t('<h3>Connection successful!</h3>You are now connected to Acquia Cloud. Please enter a name for your site to begin sending profile data.'));
    }
  }

}
