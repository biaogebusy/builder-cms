<?php

namespace Drupal\acquia_connector\Form;

use Drupal\acquia_connector\Client;
use Drupal\acquia_connector\ConnectorException;
use Drupal\acquia_connector\Controller\SpiController;
use Drupal\acquia_connector\Helper\Storage;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Acquia Connector Settings.
 *
 * @package Drupal\acquia_connector\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The private key.
   *
   * @var \Drupal\Core\PrivateKey
   */
  protected $privateKey;

  /**
   * The Acquia connector client.
   *
   * @var \Drupal\acquia_connector\Client
   */
  protected $client;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The spi backend.
   *
   * @var \Drupal\acquia_connector\Controller\SpiController
   */
  protected $spiController;

  /**
   * Constructs a \Drupal\aggregator\SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\PrivateKey $private_key
   *   The private key.
   * @param \Drupal\acquia_connector\Client $client
   *   The Acquia client.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State handler.
   * @param \Drupal\acquia_connector\Controller\SpiController $spi_controller
   *   SPI backend.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, PrivateKey $private_key, Client $client, StateInterface $state, SpiController $spi_controller) {
    parent::__construct($config_factory);

    $this->moduleHandler = $module_handler;
    $this->privateKey = $private_key;
    $this->client = $client;
    $this->state = $state;
    $this->spiController = $spi_controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('private_key'),
      $container->get('acquia_connector.client'),
      $container->get('state'),
      $container->get('acquia_connector.spi')
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
    return 'acquia_connector_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('acquia_connector.settings');
    $storage = new Storage();
    $identifier = $storage->getIdentifier();
    $key = $storage->getKey();
    $subscription = $config->get('subscription_name');

    if (empty($identifier) && empty($key)) {
      return new RedirectResponse((string) \Drupal::service('url_generator')->generateFromRoute('acquia_connector.start'));
    }

    // Check our connection to the Acquia and validate credentials.
    try {
      $this->client->getSubscription($identifier, $key);
    }
    catch (ConnectorException $e) {
      $error_message = acquia_connector_connection_error_message($e->getCustomMessage('code', FALSE));
      $ssl_available = in_array('ssl', stream_get_transports(), TRUE) && !defined('ACQUIA_CONNECTOR_TEST_ACQUIA_DEVELOPMENT_NOSSL') && $config->get('spi.ssl_verify');
      if (empty($error_message) && $ssl_available) {
        $error_message = $this->t('There was an error in validating your subscription credentials. You may want to try disabling SSL peer verification by setting the variable acquia_connector.settings:spi.ssl_verify to false.');
      }
      $this->messenger()->addError($error_message);
    }

    $form['connected'] = [
      '#markup' => $this->t('<h3>Connected to Acquia</h3>'),
    ];
    if (!empty($subscription)) {
      $form['subscription'] = [
        '#markup' => $this->t('Subscription: @sub <a href=":url">change</a>', [
          '@sub' => $subscription,
          ':url' => (string) \Drupal::service('url_generator')->generateFromRoute('acquia_connector.setup'),
        ]),
      ];
    }

    $form['identification'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Site Identification'),
      '#collapsible' => FALSE,
    ];

    $form['identification']['description']['#markup'] = $this->t('Provide a name for this site to uniquely identify it on Acquia Cloud.');
    $form['identification']['description']['#weight'] = -2;

    $form['identification']['site'] = [
      '#prefix' => '<div class="acquia-identification">',
      '#suffix' => '</div>',
      '#weight' => -1,
    ];

    $form['identification']['site']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#default_value' => $this->state->get('spi.site_name') ?? $this->spiController->getAcquiaHostedName(),
    ];

    if (!empty($form['identification']['site']['name']['#default_value']) && $this->spiController->checkAcquiaHosted()) {
      $form['identification']['site']['name']['#disabled'] = TRUE;
    }

    if ($this->spiController->checkAcquiaHosted()) {
      $form['identification']['#description'] = $this->t('Acquia hosted sites are automatically provided with a machine name.');
    }

    $form['identification']['site']['machine_name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['identification', 'site', 'name'],
      ],
      '#default_value' => $this->state->get('spi.site_machine_name'),
    ];

    if ($this->spiController->checkAcquiaHosted()) {
      $form['identification']['site']['machine_name']['#default_value'] = $this->state->get('spi.site_machine_name') ?: $this->spiController->getAcquiaHostedMachineName();
      $form['identification']['site']['machine_name']['#disabled'] = TRUE;
    }

    $form['connection'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Acquia Subscription Settings'),
      '#collapsible' => FALSE,
    ];

    // Help documentation is local unless the Help module is disabled.
    if ($this->moduleHandler->moduleExists('help')) {
      $help_url = Url::fromRoute('help.page', ['name' => 'acquia_connector'])->toString();
    }
    else {
      $help_url = Url::fromUri('https://docs.acquia.com/acquia-cloud/insight/install/')->getUri();
    }

    if (!empty($identifier) && !empty($key)) {
      $form['connection']['spi'] = [
        '#prefix' => '<div class="acquia-spi">',
        '#suffix' => '</div>',
        '#weight' => 0,
      ];

      $form['connection']['description']['#markup'] = $this->t('Allow collection and examination of the following items. <a href=":url">Learn more</a>.', [':url' => $help_url]);
      $form['connection']['description']['#weight'] = '-1';

      $form['connection']['spi']['admin_priv'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Admin privileges'),
        '#default_value' => $config->get('spi.admin_priv'),
      ];
      $form['connection']['spi']['send_node_user'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Nodes and users'),
        '#default_value' => $config->get('spi.send_node_user'),
      ];
      $form['connection']['spi']['send_watchdog'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Watchdog logs'),
        '#default_value' => $config->get('spi.send_watchdog'),
      ];
      $form['connection']['acquia_dynamic_banner'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Receive updates from Acquia Subscription'),
        '#default_value' => $config->get('spi.dynamic_banner'),
      ];
      $form['connection']['alter_variables'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Allow Insight to update list of approved variables.'),
        '#default_value' => (int) $config->get('spi.set_variables_override'),
        '#description' => $this->t('Insight can set variables on your site to recommended values at your approval, but only from a specific list of variables. Check this box to allow Insight to update the list of approved variables. <a href=":url">Learn more</a>.', [':url' => $help_url]),
      ];
      $form['#attached']['library'][] = 'acquia_connector/acquia_connector.form';
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Determines if the machine name already exists.
   *
   * @return bool
   *   FALSE.
   */
  public function exists() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('acquia_connector.settings');
    $values = $form_state->getValues();

    $this->state->set('spi.site_name', $values['name']);
    $config->set('spi.dynamic_banner', $values['acquia_dynamic_banner'])
      ->set('spi.admin_priv', $values['admin_priv'])
      ->set('spi.send_node_user', $values['send_node_user'])
      ->set('spi.send_watchdog', $values['send_watchdog'])
      ->set('spi.set_variables_override', $values['alter_variables'])
      ->save();

    // If the machine name changed, send information so we know if it is a dupe.
    if ($values['machine_name'] != $this->state->get('spi.site_machine_name')) {
      $this->state->set('spi.site_machine_name', $values['machine_name']);
    }

    parent::submitForm($form, $form_state);
  }

}
