<?php

namespace Drupal\acquia_connector\Form;

use Drupal\acquia_connector\AcquiaConnectorEvents;
use Drupal\acquia_connector\Event\AcquiaProductSettingsEvent;
use Drupal\acquia_connector\SiteProfile\SiteProfile;
use Drupal\acquia_connector\Subscription;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Acquia Connector Settings.
 *
 * @package Drupal\acquia_connector\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

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
   * @var \Drupal\acquia_connector\Subscription
   */
  protected $subscription;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Site Profile Service.
   *
   * @var \Drupal\acquia_connector\SiteProfile\SiteProfile
   */
  protected $siteProfile;

  /**
   * Reset to Defaults data.
   *
   * @var array
   */
  protected $resetData;

  /**
   * Acquia Connector Settings Form Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\PrivateKey $private_key
   *   The private key.
   * @param \Drupal\acquia_connector\Subscription $subscription
   *   The Acquia subscription service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State handler.
   * @param \Drupal\acquia_connector\SiteProfile\SiteProfile $site_profile
   *   Connector Site Profile Service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   Event Dispatcher Service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_configmanager
   *   The typed config manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, PrivateKey $private_key, Subscription $subscription, StateInterface $state, SiteProfile $site_profile, EventDispatcherInterface $dispatcher, TypedConfigManagerInterface $typed_configmanager) {
    parent::__construct($config_factory, $typed_configmanager);

    $this->moduleHandler = $module_handler;
    $this->privateKey = $private_key;
    $this->subscription = $subscription;
    $this->state = $state;
    $this->siteProfile = $site_profile;
    $this->dispatcher = $dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('private_key'),
      $container->get('acquia_connector.subscription'),
      $container->get('state'),
      $container->get('acquia_connector.site_profile'),
      $container->get('event_dispatcher'),
      $container->get('config.typed')
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
    // Redirect to Connector Setup if no subscription is active.
    if (!$this->subscription->isActive()) {
      return new RedirectResponse(
        Url::fromRoute('acquia_connector.setup_oauth')->toString()
      );
    }

    // Redirect to confirmation form when resetting network ID.
    if ($this->resetData) {
      // phpcs:ignore
      return \Drupal::formBuilder()->getForm('Drupal\acquia_connector\Form\ResetConfirmationForm');
    }

    // Start with an empty subscription.
    $subscription = $this->subscription->getSubscription(TRUE);

    $form['connected'] = [
      '#markup' => $this->t('<h3>Connected to Acquia</h3>'),
    ];

    if (!empty($subscription)) {
      $form['subscription'] = [
        '#markup' => $this->t('<strong>Subscription:</strong> @sub <a href=":url">change</a> <br />', [
          '@sub' => $subscription['subscription_name'],
          ':url' => Url::fromRoute('acquia_connector.setup_configure')->toString(),
        ]),
      ];
      if (isset($subscription['application'])) {
        $form['app_name'] = [
          '#markup' => $this->t('<strong>Application Name:</strong> @app_name <br />', [
            '@app_name' => $subscription['application']['name'],
          ]),
        ];
      }
      $form['identifier'] = [
        '#markup' => $this->t('<strong>Identifier:</strong> @identifier @overridden <br />', [
          '@identifier' => $this->subscription->getSettings()->getIdentifier(),
          '@overridden' => $this->isCloudOverridden() ? '(Overridden)' : '',
        ]),
      ];
      $form['app_uuid'] = [
        '#markup' => $this->t('<strong>Application UUID:</strong> @app_uuid', [
          '@app_uuid' => $this->subscription->getSettings()->getApplicationUuid(),
        ]),
      ];
    }

    $form['identification'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Site Identification'),
      '#collapsible' => FALSE,
    ];

    $form['identification']['description']['#markup'] = $this->t('This is the unique string used to identify this site on Acquia Cloud.');
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
      '#disabled' => TRUE,
      '#default_value' => $this->siteProfile->getSiteName($this->subscription->getSettings()->getApplicationUuid()),
    ];

    if (!empty($form['identification']['site']['name']['#default_value']) && $this->siteProfile->checkAcquiaHosted()) {
      $form['identification']['site']['name']['#disabled'] = TRUE;
    }

    if ($this->siteProfile->checkAcquiaHosted()) {
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
      '#default_value' => $this->siteProfile->getMachineName($subscription['uuid']),
    ];

    $form['identification']['site']['machine_name']['#disabled'] = TRUE;

    // Get product settings
    // Refresh the subscription from Acquia
    // Allow other modules to add metadata to the subscription.
    $event = new AcquiaProductSettingsEvent($form, $form_state, $this->subscription);

    // @todo Remove after dropping support for Drupal 8.
    if (version_compare(\Drupal::VERSION, '9.1', '>=')) {
      $this->dispatcher->dispatch($event, AcquiaConnectorEvents::ACQUIA_PRODUCT_SETTINGS);
    }
    else {
      // @phpstan-ignore-next-line
      $this->dispatcher->dispatch(AcquiaConnectorEvents::ACQUIA_PRODUCT_SETTINGS, $event);
    }

    $form = $event->getForm();
    if (isset($form['product_settings'])) {
      $form['product_settings']['#type'] = 'fieldset';
      $form['product_settings']['#title'] = $this->t("Product Specific Settings");
      $form['product_settings']['#collapsible'] = FALSE;
      $form['product_settings']['#tree'] = TRUE;
    }

    $form = parent::buildForm($form, $form_state);

    // Allow customers to reset the connection if it mismatches hosting.
    if ($this->isCloudOverridden()) {
      $form['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset to default'),
        '#button_type' => 'danger',
        '#submit' => [[$this, 'submitReset']],
      ];
    }

    return $form;
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
   * Submit handler for the Reset Defaults button.
   */
  public function submitReset(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $this->resetData = $form_state->getValues();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // If we're just resetting the values, do it first.
    if ($form_state->getValue('confirm')) {
      $this->state->deleteMultiple([
        'acquia_connector.identifier',
        'acquia_connector.key',
        'acquia_connector.application_uuid',
      ]);

      // Repopulate Settings and reset the subscription data.
      $this->subscription->populateSettings();
      $this->subscription->getSubscription(TRUE);
      $this->messenger()->addStatus($this->t('Successfully reset Acquia Connector Identifier and Key.'));
      return;
    }
    $event = new AcquiaProductSettingsEvent($form, $form_state, $this->subscription);
    // @todo Remove after dropping support for Drupal 8.
    if (version_compare(\Drupal::VERSION, '9.1', '>=')) {
      $this->dispatcher->dispatch($event, AcquiaConnectorEvents::ALTER_PRODUCT_SETTINGS_SUBMIT);
    }
    else {
      // @phpstan-ignore-next-line
      $this->dispatcher->dispatch(AcquiaConnectorEvents::ALTER_PRODUCT_SETTINGS_SUBMIT, $event);
    }

    $values = $form_state->getValues();
    $this->state->set('spi.site_name', $values['name']);

    $config = $this->config('acquia_connector.settings');
    // Save individual product settings within connector config.
    if (!empty($values['product_settings'])) {
      // Loop through each product.
      foreach ($values['product_settings'] as $product_name => $settings) {
        // Only set the setting if it changed.
        foreach ($settings['settings'] as $key => $value) {
          // Don't change the settings if the existing value matches.
          if ($form['product_settings'][$product_name]['settings'][$key]['#default_value'] === $value) {
            continue;
          }
          // Delete the setting if the value is null.
          if (empty($value)) {
            $config->clear('third_party_settings.' . $product_name . '.' . $key);
            continue;
          }
          // Save the setting if it's not empty.
          $config->set('third_party_settings.' . $product_name . '.' . $key, $value);
        }
      }
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Checks if Acquia Cloud's values are overridden.
   *
   * @return bool
   *   Determines whether the subscription matches AH values.
   */
  protected function isCloudOverridden() {
    if ($this->subscription->getProvider() !== 'acquia_cloud') {
      return FALSE;
    }
    $metadata = $this->subscription->getSettings()->getMetadata();
    $settings = $this->subscription->getSettings();
    return $metadata['ah_network_identifier'] !== $settings->getIdentifier() ||
      $metadata['ah_network_key'] !== $settings->getSecretKey() ||
      $metadata['AH_APPLICATION_UUID'] !== $settings->getApplicationUuid();
  }

}
