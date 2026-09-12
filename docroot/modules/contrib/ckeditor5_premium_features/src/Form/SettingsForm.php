<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Form;

use Drupal\ckeditor5_premium_features\CKEditorPremiumPluginsCheckerTrait;
use Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface;
use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\editor\Entity\Editor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the form for the main module & submodule configuration.
 */
class SettingsForm extends ConfigFormBase {

  const PREMIUM_FEATURES_CONFIG_NAME = 'ckeditor5_premium_features.settings';

  /**
   * Required length of the Environment ID.
   */
  const ENVIRONMENT_ID_LENGTH = 20;

  /**
   * Required length of the License key.
   */
  const LICENSE_KEY_MIN_LENGTH = 48;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface $configHandler
   *   Module settings handler.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              TypedConfigManagerInterface $typedConfigManager,
                              protected SettingsConfigHandlerInterface $configHandler,
                              protected ModuleHandlerInterface $moduleHandler,
                              protected LibraryVersionChecker $libraryVersionChecker,) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  use CKEditorPremiumPluginsCheckerTrait;
  use StringTranslationTrait;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('ckeditor5_premium_features.config_handler.settings'),
      $container->get('module_handler'),
      $container->get('ckeditor5_premium_features.core_library_version_checker'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor5_premium_features_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      self::PREMIUM_FEATURES_CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $user_input = $form_state->getUserInput();
    $auth_type = $user_input['auth_type'] ?? NULL;

    $form['configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Premium features configuration'),
      '#open' => TRUE,
      '#description' =>
      $this->t("Premium features will only work if configured correctly. If you have not subscribed yet, you can start <a href='@trial'>a free trial</a>.", ['@trial' => 'https://orders.ckeditor.com/trial/premium-features'])
        . '<br>'
          // @todo define the documentation URL.
        . $this->t("Follow the <a href='@documentation'>dedicated documentation for Drupal</a> as most of the steps necessary to run premium features have been already included in this module.", ['@documentation' => 'https://www.drupal.org/docs/contributed-modules/ckeditor-5-premium-features/how-to-install-and-set-up-the-module#s-adding-credentials-to-drupal']),
    ];

    $configuration = [];

    $dashboard_url = 'https://dashboard.ckeditor.com/';

    if ($this->libraryVersionChecker->isLibraryVersionHigherOrEqual('44.0.0')) {
      $licenseKeyDescription = $this->t('The license key is required only for Revision History, Track changes, Comments (without real-time collaboration), and Productivity Pack. Use the license key <strong>for versions 44.0.0 and above</strong>.');
    }
    elseif ($this->libraryVersionChecker->isLibraryVersionHigherOrEqual('38.0.0')) {
      $licenseKeyDescription = $this->t('The license key is required only for Revision History, Track changes, Comments (without real-time collaboration), and Productivity Pack. Use the license key <strong>for versions 38.0.0 up to 43.x.x</strong>.');
    }
    else {
      $licenseKeyDescription = $this->t('The license key is required only for Revision History, Track changes and Comments (without real-time collaboration). Use the license key <strong>for versions up to 37.1.0</strong>.');
    }

    $configuration['license_key'] = [
      '#type' => 'textarea',
      '#title' => $this->t('License key'),
      '#description' => $licenseKeyDescription,
    ];

    $configuration['add_key_to_all_instances'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add the license key to all CKEditor instances'),
      '#description' => $this->t('If enabled, the license key will be used for all CKEditor 5 instances. If disabled, the license key will be used only for CKEditor 5 instances with enabled Premium Features (<strong>%formats</strong>). </br> Editor instances without the license key will display the "Powered by CKEditor" logo, but will not report the license usage. This affects only CKEditor 5 v44.0.0 and later.', ['%formats' => $this->getTextFormatsWithPremiumFeatures()]),
      '#default_value' => $this->configHandler->isAddKeyToAllInstancesEnabled(),
    ];

    $configuration['auth_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Authorization type'),
      '#options' => [
        'none' => $this->t('Not set'),
        'key' => $this->t('Access key'),
        'dev_token' => $this->t('Development token'),
      ],
      '#default_value' => 'none',
      '#description' => $this->t('Select the authorization suitable type for your features. The access key-based authorization is highly recommended and the best option in production environment. The development token should rather be used for testing purposes.')
      . '<br />'
      . $this->t('The authorization credentials are required for Real-time collaboration or Import from Word, and optional for Export to Word/PDF to generate documents without the watermark.'),
    ];

    $configuration['env'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Environment ID'),
      '#description' =>
      $this->t('The environment management panel can be found in <a href="@dashboard">CKEditor dashboard</a>.', ['@dashboard' => $dashboard_url]),
      '#states' => [
        'visible' => [
          'select[name="auth_type"]' => ['value' => 'key'],
        ],
      ],
    ];

    $configuration['access_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access key'),
      '#required' => $auth_type === 'key',
      '#description' => $this->t('The access key for the environment can be found in the <a href="@dashboard">CKEditor dashboard</a>.', ['@dashboard' => $dashboard_url]),
      '#states' => [
        'visible' => [
          'select[name="auth_type"]' => ['value' => 'key'],
        ],
      ],
    ];

    $configuration['dev_token_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Development token URL'),
      '#description' => $this->t('The development token URL should be used with care as it does not provide sufficient permission validation. While it is good for testing, it is highly recommended to specify Environment ID and Access Key instead for production environments.'),
      '#attributes' => [
        'placeholder' => 'https://',
      ],
      '#states' => [
        'visible' => [
          'select[name="auth_type"]' => ['value' => 'dev_token'],
        ],
      ],
    ];

    $configuration['dev_token_accept'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I understand the consequences of using a development token URL.'),
      '#states' => [
        'required' => [
          'select[name="auth_type"]' => ['value' => 'dev_token'],
        ],
        'visible' => [
          'select[name="auth_type"]' => ['value' => 'dev_token'],
        ],
      ],
    ];

    $configuration['organization_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization ID'),
      '#description' =>
        $this->t('The organization ID can be found in the <a href="@dashboard">CKEditor dashboard</a>.', ['@dashboard' => $dashboard_url])
        . '<br>'
        . $this->t('Required for Real-time collaboration and API requests.'),
    ];

    $configuration['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#description' =>
        $this->t('The API Key can be found in the <a href="@dashboard">CKEditor dashboard</a>.', ['@dashboard' => $dashboard_url])
        . '<br>'
        . $this->t('Required for Real-time collaboration and API requests.'),
    ];

    $this->setDefaultValues($configuration);

    $form['configuration'] = $configuration + $form['configuration'];

    $form['dependencies'] = $this->buildDependencyInstallationSection();

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
      '#description' =>
      $this->t('CKEditor Premium Features needs to load additional plugins (“DLLs”) in order to run. By default this module will detect the version of CKEditor your website is running and load required plugins from a CDN automatically.')
      . '<br>'
      . $this->t('Specify the DLL packages location <strong>only</strong> if you host the DLL packages by yourself. Contact us in case of any questions.'),
    ];

    $advanced['web_socket_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Socket URL'),
      '#default_value' => $this->configHandler->getDefaultWebSocketUrl(),
      '#attributes' => [
        'placeholder' => $this->configHandler->getDefaultWebSocketUrl(),
      ],
      '#description' =>
      $this->t('The web socket url can be found in the <a href="@dashboard">CKEditor dashboard</a>.', ['@dashboard' => $dashboard_url])
      . '<br />'
      . 'You can leave this field empty - system will automatically generate this URL using Organization ID field',
    ];

    $advanced['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API base URL'),
      '#default_value' => $this->configHandler->getDefaultApiUrl(),
      '#attributes' => [
        'placeholder' => $this->configHandler->getDefaultApiUrl(),
      ],
      '#description' =>
        $this->t('The API base URL can be found in the <a href="@dashboard">CKEditor dashboard</a>.', ['@dashboard' => $dashboard_url])
        . '<br />'
        . 'You can leave this field empty - system will automatically generate this URL using Organization ID and Environment ID fields',
    ];

    $advanced['dll_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DLL packages location'),
      '#description' => $this->t('Leave this field empty unless you know what you are doing. The path must end by "/"<br />This field supports additional token: "@token" - replaced dynamically with the version of your CKEditor.', [
        '@token' => SettingsConfigHandlerInterface::DLL_PATH_VERSION_TOKEN,
      ]),
      '#default_value' => $this->configHandler->getDefaultDllLocation(),
      '#attributes' => [
        'placeholder' => $this->configHandler->getDefaultDllLocation(),
      ],
    ];

    $this->setDefaultValues($advanced);

    $form['advanced'] = $advanced + $form['advanced'];

    $form['appearance'] = [
      '#type' => 'details',
      '#title' => $this->t('Appearance settings'),
      '#open' => FALSE,
      '#description' => $this->t('Additional appearance settings'),
    ];

    $appearance['alter_node_form_css'] = [
      '#type' => 'checkbox',
      '#title' => t('Allow the module to alter the default Drupal theme CSS to make the editing experience better.'),
      '#default_value' => $this->configHandler->isAlterNodeFormCssEnabled(),
      '#description' => t('Provides more width (space) for CKEditor in the Claro theme. <br/> <strong>Cache has to be flushed after changing this setting.</strong>'),
    ];

    $form['appearance'] += $appearance;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $this->processCleanValues($form_state);

    $access_key = $form_state->getValue('access_key', FALSE);
    $env = $form_state->getValue('env', FALSE);
    $auth_type = $form_state->getValue('auth_type');
    $license_key = $form_state->getValue('license_key');

    if (!empty($license_key)) {
      [$headersB64, $payloadB64] = explode('.', $license_key);
      $payload = json_decode(base64_decode($payloadB64), TRUE);

      if (isset($payload['removeFeatures']) && !empty($payload['removeFeatures'])) {
        $form_state->setErrorByName('license_key', $this->t('CKEditor 5 Free plan licenses are not supported by this module. Leave this field empty unless you have a paid Premium Features license.'));
      }
    }

    if (!empty($license_key) && strlen($license_key) < self::LICENSE_KEY_MIN_LENGTH) {
      $form_state->setErrorByName('license_key', $this->t('@name length is invalid (minimum @num characters required)', [
        '@name' => 'License key',
        '@num' => self::LICENSE_KEY_MIN_LENGTH,
      ]));
    }

    if ($auth_type == 'key') {
      if (!empty($env) && strlen($env) != self::ENVIRONMENT_ID_LENGTH) {
        $form_state->setErrorByName('env', $this->t('@name length is invalid (@num characters required)', [
          '@name' => 'Environment ID',
          '@num' => self::ENVIRONMENT_ID_LENGTH,
        ]));
      }
    }

    if ($this->isRealtimeSettingsRequired() && !in_array($auth_type, [
      'key',
      'dev_token',
    ])) {
      $form_state->setErrorByName('auth_type', $this->t('You need to choose the authorization type in order to use Realtime Collaboration features'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config(self::PREMIUM_FEATURES_CONFIG_NAME);
    $clean_values = $this->processCleanValues($form_state);

    // Let's make sure the path ends with the trailing slash.
    if (!empty($clean_values['dll_location'])) {
      $clean_values['dll_location'] = rtrim($clean_values['dll_location'], ' /') . '/';
    }

    $dll_changed = $config->get('dll_location') !== $clean_values['dll_location'];

    $config
      ->setData($clean_values)
      ->save();

    $invalidate_tags = [
      'ckeditor5_plugins',
      'editor_plugins',
      'filter_plugins',
    ];

    if ($dll_changed) {
      $invalidate_tags[] = 'library_info';
    }

    Cache::invalidateTags($invalidate_tags);

    parent::submitForm($form, $form_state);
  }

  /**
   * Sets the default value on the form elements.
   *
   * It is taking the config value if present.
   *
   * @param array $elements
   *   The form elements to be processed.
   */
  private function setDefaultValues(array &$elements): void {
    $config = $this->config(self::PREMIUM_FEATURES_CONFIG_NAME);
    foreach ($elements as $key => $element) {
      $elements[$key]['#default_value'] = $config->get($key) ?? ($element['#default_value'] ?? NULL);
    }
  }

  /**
   * Additionally cleans up the form state values.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object that values should be cleaned up additionally.
   *
   * @return array
   *   Form state clean values.
   */
  protected function processCleanValues(FormStateInterface $form_state): array {
    $clean_values = $form_state->cleanValues()->getValues();

    foreach ($clean_values as &$value) {
      if (is_string($value)) {
        $value = trim($value);
      }
    }
    $form_state->setValues($clean_values);

    return $clean_values;
  }

  /**
   * Checks if the Realtime Collaboration module is enabled.
   */
  protected function isRealtimeSettingsRequired(): bool {
    return $this->moduleHandler->moduleExists('ckeditor5_premium_features_realtime_collaboration');
  }

  /**
   * Returns the labels of text formats with premium features enabled.
   *
   * @return string
   *   The text formats with premium features enabled.
   */
  private function getTextFormatsWithPremiumFeatures(): string {
    $editors = Editor::loadMultiple();
    $labels = [];
    foreach ($editors as $editor) {
      if ($editor->get('editor') === 'ckeditor5' && $this->hasPremiumFeaturesEnabled($editor->get('settings'))) {
        $labels[] = $editor->label();
      }
    }

    return $labels ? implode(', ', $labels) : '-none-';
  }

  private function buildDependencyInstallationSection($isReloading = FALSE): array {
    $form = [
      '#type' => 'details',
      '#title' => $this->t('Dependencies installation'),
      '#open' => FALSE,
    ];
    if (!$this->moduleHandler->moduleExists('package_manager')) {
      $form['#description'] = $this->t('<p>This section can be used to install external dependencies required by some of the Premium Features directly from the Admin Dashboard. It requires a package_manager module to be installed in order to become enabled.</p>
                                               <p>We suggest visiting our Configuration Guide to learn how to install required dependencies using Composer</p>');
      return $form;
    }
    if (!$this->currentUser()->hasPermission('administer modules')) {
      $form['#description'] = $this->t('CKEditor 5 Premium Features requires additional dependencies to be installed. You need to have Administer Modules permission granted in order to be able to install dependencies.');
      return $form;
    }

    /** @var \Drupal\package_manager\ComposerInspector $inspector */
    $inspector = \Drupal::service('Drupal\package_manager\ComposerInspector');
    $pathLocator = \Drupal::service('Drupal\package_manager\PathLocator');
    $dir = $pathLocator->getProjectRoot();
    $packages = $inspector->getInstalledPackagesList($dir)->getArrayCopy();

    $form['#open'] = $isReloading;
    $form['#description'] = $this->t('This section allows installation of external dependencies required by some of the Premium Features to work. Please do not leave this page during the installation process.');
    $form['#attributes'] = [
      'id' => ['ckeditor5-dependency-install-container'],
    ];
    $form['#attached'] = [
      'library' => ['ckeditor5_premium_features/dependency-install'],
    ];

    if ($isReloading) {
      $form['messages'] = [
        '#type' => 'container',
        '#attributes' => [
          'data-drupal-messages' => '',
          'class' => ['ckeditor5-dependency-install-messages'],
        ],
      ];
    }

    $default = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['ckeditor5-dependency-install-wrapper'],
      ],
      'button' => [
        '#type' => 'button',
        '#value' => $this->t('Install'),
        '#attributes' => [
          'class' => ['ckeditor5-dependency-install']
        ],
        '#prefix' => '<div class="ckeditor5-dependency-install-button">',
        '#suffix' => '<div class="ajax-progress ajax-progress--throbber"><div class="ajax-progress__throbber">&nbsp;</div><div class="ajax-progress__message">Installing...</div></div></div>',
        '#weight' => 10,
      ],
    ];

    $dependencies = [
      'htmldiff' => [
        'name' => 'caxy/php-htmldiff',
        'version' => '0.1.16',
        'description' => $this->t('This dependency is required for the <strong>Non-realtime collaboration</strong> and <strong>notifications</strong> features.'),
      ],
      'jwt' => [
        'name' => 'firebase/php-jwt',
        'version' => '6.11.1',
        'description' => $this->t('This dependency is required for the <strong>Realtime collaboration, Exporters</strong> and <strong>Import from Word</strong> features.'),
      ],
      'openai' => [
        'name' => 'openai-php/client',
        'version' => '0.12.0',
        'description' => $this->t('This dependency is required for the <strong>AI Assistant</strong> in case <strong>OpenAI</strong> or <strong>AzureAI</strong> providers are used.'),
      ],
      'aws' => [
        'name' => 'aws/aws-sdk-php',
        'version' => '3.343.11',
        'description' => $this->t('This dependency is required for the <strong>AI Assistant</strong> in case <strong>AWS Bedrock</strong> provider is used.'),
      ],
    ];

    foreach ($dependencies as $key => $package) {
      $name = $package['name'];
      $form[$key] = $default;
      $form[$key]['package_name'] = [
        '#markup' => "<h5 class='ckeditor5-dependency-install-package-name'>" . $name . "</h5>",
        '#weight' => 0,
      ];
      $form[$key]['description'] = [
        '#markup' => $package['description'],
        '#weight' => 1,
        '#prefix' => '<div class="ckeditor5-dependency-install-description">',
        '#suffix' => '</div>',
      ];
      $form[$key]['button']['#value'] = $this->t('Install');
      $form[$key]['button']['#attributes']['data-package'][] = $name;
      $form[$key]['button']['#attributes']['data-package-version'][] = $package['version'];
      if (isset($packages[$name])) {
        if (version_compare($package['version'], str_replace('v', '', $packages[$name]->version), '<=')) {
          $form[$key]['button']['#attributes']['disabled'] = 'disabled';
          $form[$key]['button']['#value'] = $this->t('Up to date');
        } else {
          $form[$key]['button']['#value'] = $this->t('Update');
        }

      }
    }

    $form['reload'] = [
      '#type' => 'button',
      '#value' => $this->t('Reload'),
      '#attributes' => [
        'id' => ['edit-reload'],
        'class' => ['ckeditor5-dependency-install-reload-button'],
      ],
      '#ajax' => [
        'callback' => [$this, 'ajaxReload'],
        'wrapper' => 'ckeditor5-dependency-install-container',
        'event' => 'click',
      ],
    ];

    return $form;
  }

  public function ajaxReload(array &$form, FormStateInterface $form_state): array {
    return $this->buildDependencyInstallationSection(TRUE);
  }

}
