<?php

declare(strict_types=1);

namespace Drupal\acquia_connector\Form;

use Drupal\acquia_connector\AuthService;
use Drupal\acquia_connector\Client\ClientFactory;
use Drupal\acquia_connector\SiteProfile\SiteProfile;
use Drupal\acquia_connector\Subscription;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Form for selecting application to use for Connector.
 */
final class ConfigureApplicationForm extends FormBase {

  /**
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The site profile.
   *
   * @var \Drupal\acquia_connector\SiteProfile\SiteProfile
   */
  protected SiteProfile $siteProfile;

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_connector\Client\ClientFactory
   */
  protected ClientFactory $clientFactory;

  /**
   * The subscription.
   *
   * @var \Drupal\acquia_connector\Subscription
   */
  protected Subscription $subscription;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Acquia Connector Auth Service.
   *
   * @var \Drupal\acquia_connector\AuthService
   */
  protected AuthService $authService;


  /**
   * Form builder to return API credentials form.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected FormBuilder $formBuilder;

  /**
   * Reset Credentials Data.
   *
   * @var array
   */
  protected $resetData;

  /**
   * Constructs a new ConfigureApplicationForm object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key/value store.
   * @param \Drupal\acquia_connector\SiteProfile\SiteProfile $site_profile
   *   The site profile.
   * @param \Drupal\acquia_connector\Client\ClientFactory $client_factory
   *   The client factory.
   * @param \Drupal\acquia_connector\Subscription $subscription
   *   The subscription.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\acquia_connector\AuthService $auth_service
   *   Acquia Connector Auth Service.
   * @param \Drupal\Core\Form\FormBuilder $form_builder
   *   The form builder service.
   */
  public function __construct(StateInterface $state, SiteProfile $site_profile, ClientFactory $client_factory, Subscription $subscription, LoggerInterface $logger, AuthService $auth_service, FormBuilder $form_builder) {
    $this->state = $state;
    $this->siteProfile = $site_profile;
    $this->clientFactory = $client_factory;
    $this->subscription = $subscription;
    $this->logger = $logger;
    $this->authService = $auth_service;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = new self(
      $container->get('state'),
      $container->get('acquia_connector.site_profile'),
      $container->get('acquia_connector.client.factory'),
      $container->get('acquia_connector.subscription'),
      $container->get('acquia_connector.logger_channel'),
      $container->get('acquia_connector.auth_service'),
      $container->get('form_builder')
    );
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'acquia_connector_configure_application_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Redirect to confirmation form when resetting network ID.
    if ($this->resetData) {
      return $this->formBuilder->getForm(ResetConfirmationForm::class);
    }

    try {
      $response = $this->clientFactory->getCloudApiClient()->get('/api/applications');
      $data = Json::decode((string) $response->getBody());
      $applications = [];
      foreach ($data['_embedded']['items'] as $item) {
        if (trim($item['subscription']['name']) !== trim($item['name'])) {
          // Format for ACSF Sites.
          if (preg_match('/.+?(?= - ACSF)/', trim($item['name']), $sub_name)) {
            // For ACSF sites, remove the duplicate name in the subscription.
            $applications[$item['uuid']] = $sub_name[0] . ': ' . substr($item['name'], strlen($sub_name[0]) + 2);
          }
          else {
            $applications[$item['uuid']] = trim($item['subscription']['name'] . ': ' . $item['name']);
          }
        }
        else {
          $applications[$item['uuid']] = trim($item['name']);
        }
      }
      asort($applications);

      // We have a token, but API keys are missing, create them.
      // Note, this code will become redundant once idp oauth is removed.
      if (empty($this->state->get('acquia_connector.credentials', ''))) {
        $response = $this->clientFactory->getCloudApiClient()->post('/api/account/tokens', [
          'body' => Json::encode([
            'label' => 'Connector (' . $this->getRequest()->getHost() . ') - Do Not Remove',
          ]),
          'headers' => [
            'Content-Type' => 'application/json, version=2',
            'Accept' => 'application/json',
          ],
        ]);
        if ($response->getStatusCode() == 201) {
          $credentials = Json::decode((string) $response->getBody());
          $this->state->set('acquia_connector.credentials', Json::encode($credentials));
        }
      }
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('We could not retrieve account data, please re-authorize with your Acquia Cloud account. For more information check <a target="_blank" href=":url">this link</a>.', [
        ':url' => Url::fromUri('https://docs.acquia.com/cloud-platform/known-issues/#unable-to-log-in-through-acquia-connector')->getUri(),
      ]));
      $this->logger->error('Unable to get applications from Acquia Cloud: @error', [
        '@error' => $e->getMessage(),
      ]);
      return new RedirectResponse(Url::fromRoute('acquia_connector.setup_oauth')->toString());
    }
    // Set some defaults if we're hosted on Acquia Cloud.
    $default_uuid = '';
    if ($this->subscription->getProvider() === 'acquia_cloud') {
      $default_uuid = $this->subscription->getSettings()->getMetadata('AH_APPLICATION_UUID');
    }

    if (count($applications) === 0) {
      $this->messenger()->addError($this->t('No subscriptions were found for your account.'));
      return new RedirectResponse(Url::fromRoute('acquia_connector.setup_oauth')->toString());
    }
    if (count($applications) === 1) {
      // Don't allow users on cloud to inadvertently override the default sub.
      if ($default_uuid && key($applications) !== $default_uuid) {
        $this->messenger()->addError($this->t("Unable to set Subscription: User does not have access to this application."));
        return new RedirectResponse(Url::fromRoute('acquia_connector.setup_oauth')->toString());
      }
      $form_state->setValue('application', key($applications));
      $this->submitForm($form, $form_state);
      $redirect = $form_state->getRedirect();
      if ($redirect instanceof Url) {
        return new RedirectResponse($redirect->setAbsolute()->toString(), Response::HTTP_SEE_OTHER);
      }
    }

    $form['application'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $applications,
      '#default_value' => $default_uuid,
      '#title' => $this->t('Application'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Set application'),
      ],
      'reauthenticate' => [
        '#type' => 'submit',
        '#button_type' => 'danger',
        '#value' => $this->t('Re-authenticate'),
        '#submit' => [[$this, 'submitReset']],
      ]
    ];

    return $form;
  }

  /**
   * Submit handler for the Reset Defaults button.
   */
  public function submitReset(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $this->resetData = $form_state->getValues();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Reset Authentication Creds.
    if ($form_state->getValue('confirm')) {
      $this->authService->resetCredentials();
      $form_state->setRedirect('acquia_connector.setup_oauth');
      return;
    }

    $values = $form_state->getValues();
    $application_uuid = $values['application'];
    $client = $this->clientFactory->getCloudApiClient();
    try {
      $keys = $client->get("/api/applications/$application_uuid/settings/keys");
      $data_keys = Json::decode((string) $keys->getBody());
    }
    catch (\Throwable $e) {
      $this->messenger()->addError('We encountered an error when retrieving information for the selected application. Try logging into Acquia Cloud again.');
      $form_state->setRedirect('acquia_connector.setup_oauth');
      return;
    }

    // Setup form uses the state system, update state.
    $this->state->set('acquia_connector.identifier', $data_keys['acquia_connector']['identifier']);
    $this->state->set('acquia_connector.key', $data_keys['acquia_connector']['key']);
    $this->state->set('acquia_connector.application_uuid', $application_uuid);
    $this->state->set('spi.site_name', $this->siteProfile->getSiteName($application_uuid));

    $this->subscription->populateSettings();
    $subscription_data = $this->subscription->getSubscription(TRUE);

    if ($subscription_data) {
      $form_state->setRedirect('acquia_connector.settings');
    }

    if ($subscription_data['active']) {
      $this->messenger()->addStatus($this->t('<h3>Connection successful!</h3>You are now connected to Acquia Cloud.'));
    }
  }

}
