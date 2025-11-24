<?php

namespace Drupal\acquia_connector\Form;

use Drupal\acquia_connector\AuthService;
use Drupal\acquia_connector\Client\ClientFactory;
use Drupal\acquia_connector\Subscription;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Form for Acquia Credentials.
 */
class ApiKeyCredentialForm extends FormBase {

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
   * The auth service.
   *
   * @var \Drupal\acquia_connector\AuthService
   */
  protected AuthService $authService;

  /**
   * The messenger service object.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  protected $moduleExtensionList;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\acquia_connector\Client\ClientFactory $client_factory
   *   The Acquia client.
   * @param \Drupal\Core\State\StateInterface $state
   *   Drupal State Service.
   * @param \Drupal\acquia_connector\Subscription $subscription
   *   Acquia Connector Subscription service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service object.
   */
  public function __construct(ClientFactory $client_factory, StateInterface $state, Subscription $subscription, AuthService $auth_service, MessengerInterface $messenger, ModuleExtensionList $module_extension_list) {
    $this->clientFactory = $client_factory;
    $this->state = $state;
    $this->subscription = $subscription;
    $this->authService = $auth_service;
    $this->messenger = $messenger;
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_connector.client.factory'),
      $container->get('state'),
      $container->get('acquia_connector.subscription'),
      $container->get('acquia_connector.auth_service'),
      $container->get('messenger'),
      $container->get('extension.list.module')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_connector_settings_apikey_credentials';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $credentials = $this->state->get('acquia_connector.credentials', '');
    $credentials = Json::decode($credentials);

    // Wrapper for the entire form.
    $form['#prefix'] = '<div class="connector-form-wrapper">';
    $form['#suffix'] = '</div>';

    // Create a container for the form and image.
    $form['layout'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-image-layout']],
    ];

    // Left Section (Form Fields)
    $form['layout']['left'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['left-section']],
    ];

    $form['layout']['left']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $credentials['api_key'] ?? '',
      '#description' => $this->t('Enter API Key.'),
      '#required' => TRUE,
    ];
    $form['layout']['left']['api_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('Secret'),
      '#default_value' => $credentials['api_secret'] ?? '',
      '#description' => $this->t('Enter API Secret.'),
      '#required' => TRUE,
    ];
    // Create link to Acquia Cloud that open in a modal.
    $form['layout']['left']['help-how'] = [
      '#type' => 'markup',
      '#markup' => "You must first generate a new API key/secret from <a target='_blank' href='https://cloud.acquia.com/a/profile/tokens'>Acquia Cloud</a> and paste it in the form above.",
    ];
    $form['layout']['left']['actions'] = ['#type' => 'actions'];
    $form['layout']['left']['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Authenticate'),
    ];
    $form['layout']['left']['actions']['manual'] = [
      '#type' => 'link',
      '#title' => $this->t('Configure manually'),
      '#url' => Url::fromRoute('acquia_connector.setup_manual'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    // Right Section (Image)
    $form['layout']['right'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['right-section']],
    ];
    $module_path = $this->moduleExtensionList->get('acquia_connector')->getPath();
    $form['layout']['right']['help'] = [
      '#type' => 'markup',
      '#markup' => "<img src='/$module_path/images/tokens.gif' alt='Right Side Image' class='form-image' />",
    ];
    // Attach the custom library defined in acquia_connector.libraries.yml.
    $form['#attached']['library'][] = 'acquia_connector/acquia_connector.form';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save Credentials to state, only if a new value was added.
    $values = ['api_key', 'api_secret'];
    $credentials = [];
    foreach ($values as $value) {
      $credentials[$value] = $form_state->getValue($value);
    }
    $this->state->set('acquia_connector.credentials', Json::encode($credentials));
    // Redirect to the path without the suffix.
    $form_state->setRedirect('acquia_connector.setup_configure');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $response = $this->authService->authenticateWithApi($form_state->getValue('api_key'), $form_state->getValue('api_secret'));
    if (!$response['success']) {
      $body = Json::decode($response['response']->getBody());
      $message = $body['error_description'] ?? $response['message'];
      if ($message) {
        $this->messenger->addError($message);
      }
      $form_state->setErrorByName('api_key');
      $form_state->setErrorByName('api_secret');
    }
  }

}
