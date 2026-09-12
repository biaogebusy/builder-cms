<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_wproofreader\Form;

use Drupal\ckeditor5_premium_features_wproofreader\Utility\WebSpellCheckerHandler;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the configuration form of the "WProofreader" feature.
 */
class SettingsForm extends ConfigFormBase {

  const WPROOFREADER_SETTINGS_ID = 'ckeditor5_premium_features_wproofreader.settings';
  const DEFAULT_WSCBUNDLE_URL = 'https://svc.webspellchecker.net/spellcheck31/wscbundle/wscbundle.js';
  const WSC_DEFAULT_SERVICE_TYPE = 'default';
  const WSC_ON_PREMISE_SERVICE_TYPE = 'on_premise';

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor5_premium_features_wproofreader_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      self::WPROOFREADER_SETTINGS_ID,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function __construct(protected WebSpellCheckerHandler $webSpellCheckerHandler, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $moduleHandler, TypedConfigManagerInterface $typedConfigManager) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ckeditor5_premium_features_wproofreader.wsc_handler'),
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('config.typed'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state):array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config(self::WPROOFREADER_SETTINGS_ID);

    $langOptions = [];

    if ($form_state->isRebuilding()) {
      $form_state->clearErrors();
      $serviceId = $form_state->getValue('service_id');
      if ($serviceId) {
        $availableLanguages = $this->webSpellCheckerHandler->getAvailableLanguages($serviceId);
        if (!empty($availableLanguages)) {
          $langOptions = $availableLanguages;
        }
      }
    }
    else {
      $serviceId = $config->get('service_id');
      if ($serviceId) {
        $langOptions = $this->getLangOptions($serviceId);
      }
    }

    $form['service_id_message_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'service-id-message-container',
      ],
    ];

    $form['service_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service ID'),
      '#description' => $this->t('Activation key received upon subscription, required for WProofreader service use.'),
      '#default_value' => $serviceId ?? '',
      '#states' => [
        'required' => [
          ':input[name="service_type"]' => ['value' => self::WSC_DEFAULT_SERVICE_TYPE],
        ],
      ],
      '#ajax' => [
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Validating Service ID...'),
        ],
        'callback' => '::handleServiceIdField',
        'wrapper' => 'language-container',
        'method' => 'replaceWith',
        'disable-refocus' => TRUE,
      ],
    ];

    $form['language_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'language-container',
        'style' => empty($langOptions) ? 'display: none;' : '',
      ],
    ];
    if (!empty($langOptions)) {
      $form['language_container']['lang_code'] = [
        '#type' => 'select',
        '#title' => $this->t('Language'),
        '#options' => $langOptions,
        '#default_value' => $config->get('lang_code') ?? 'auto',
        '#attributes' => ['id' => 'lang-code'],
      ];
    }
    if (!$this->moduleHandler->moduleExists('ckeditor5_plugin_pack_free_wproofreader')) {
      $form['company_dictionaries'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Company dictionaries'),
        '#default_value' => $config->get('company_dictionaries') ?? '',
        '#description' => $this->t('Comma-separated list of dictionary IDs to load with WProofreader. If left empty, all enabled dictionaries will be loaded. Manage dictionaries at <a href="@link" target="_blank">custom dictionary page</a>.', [
          '@link' => 'https://app.wproofreader.com/custom-dictionary',
        ]),
      ];
      $permissionsUrl = Link::createFromRoute('permissions', 'user.admin_permissions.module', ['modules' => 'ckeditor5_premium_features_wproofreader'])->toString();
      $form['settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Settings'),
        '#open' => TRUE,
      ];
      $form['settings']['user_default'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('User default settings'),
      ];
      $form['settings']['user_default']['description'] = [
        '#markup' => $this->t("The default WProofreader settings are applied to all new users. However, users can modify these settings from the WProofreader UI, and their changes will apply only to them, as they are saved in the browser's local storage. To restrict users from modifying these settings, update the user %permissions", ['%permissions' => $permissionsUrl]),
      ];
      $form['settings']['user_default']['general_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('General check types'),
      ];
      $form['settings']['user_default']['general_settings']['spellingSuggestions'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Spelling suggestions'),
        '#default_value' => $config->get('spellingSuggestions') ?? TRUE,
      ];
      $form['settings']['user_default']['general_settings']['grammarSuggestions'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Grammar suggestions'),
        '#default_value' => $config->get('grammarSuggestions') ?? TRUE,
      ];
      $form['settings']['user_default']['general_settings']['styleGuideSuggestions'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Style guide suggestions'),
        '#default_value' => $config->get('styleGuideSuggestions') ?? TRUE,
      ];
      $form['settings']['user_default']['general_settings']['autocorrect'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Correct spelling automatically'),
        '#default_value' => $config->get('autocorrect') ?? TRUE,
      ];
      $form['settings']['user_default']['general_settings']['autocomplete'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Autocomplete suggestions'),
        '#default_value' => $config->get('autocomplete') ?? FALSE,
      ];
      $form['settings']['user_default']['ignore_options'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Spelling ignore options'),
      ];
      $form['settings']['user_default']['ignore_options']['ignoreAllCapsWords'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Ignore all-caps words'),
        '#default_value' => $config->get('ignoreAllCapsWords') ?? FALSE,
        '#description' => $this->t("All caps words like 'EXAMPLE'."),
      ];
      $form['settings']['user_default']['ignore_options']['ignoreDomainNames'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Ignore domain names'),
        '#default_value' => $config->get('ignoreDomainNames') ?? TRUE,
        '#description' => $this->t("Domain names like 'http://example.com'."),
      ];
      $form['settings']['user_default']['ignore_options']['ignoreWordsWithMixedCases'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Ignore words with mixed case'),
        '#default_value' => $config->get('ignoreWordsWithMixedCases') ?? FALSE,
        '#description' => $this->t("Words with mixed case like 'eXaMpLe'."),
      ];
      $form['settings']['user_default']['ignore_options']['ignoreWordsWithNumbers'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Ignore words with numbers'),
        '#default_value' => $config->get('ignoreWordsWithNumbers') ?? TRUE,
        '#description' => $this->t("Words with numbers like 'example7'."),
      ];

      $form['settings']['advanced'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Advanced features'),
      ];
      $form['settings']['advanced']['aiWritingAssistant'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('AI writing assistant'),
        '#description' => $this->t('Enables an AI-powered assistant to refine and adapt text in multiple ways. Subject to the <a href="https://webspellchecker.com/legal/terms-of-service/" target="_blank">Terms of Service</a>.'),
        '#default_value' => $config->get('aiWritingAssistant') ?? FALSE,
      ];
    }

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['custom'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Configuration options'),
      '#default_value' => $config->get('custom') ?? '{}',
      '#description' => $this->t('Specify additional configuration options in JSON format to customize WProofreader’s behavior. For example: {"autoStartup": false, "theme": "dark"}<br />See the full list of configurable options <a href="https://webspellchecker.com/docs/api/wscbundle/Options.html" target="_blank">here</a>.<br />These options will override values set in the configuration form.'),
    ];

    $form['advanced']['service_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('WProofreader deployment options'),
      '#options' => [
        self::WSC_DEFAULT_SERVICE_TYPE => $this->t('Use default endpoint (Cloud service) </br>
            <div class="form-item__description">Uses WebSpellChecker\'s cloud service by default. No additional configuration needed. Access and use are governed by <a href="@terms_url" target="_blank">Terms of Service.</a></div>', ['@terms_url' => 'https://webspellchecker.com/legal/terms-of-service/']),
        self::WSC_ON_PREMISE_SERVICE_TYPE => $this->t('Use self-hosted version endpoint </br>
            <div class="form-item__description">For deployment in your own environment. Requires custom endpoint setup. Ensures local text processing, keeping data internal.</div>'),
      ],
      '#default_value' => $config->get('service_type') ?? self::WSC_DEFAULT_SERVICE_TYPE,
    ];

    $form['advanced']['on_premise_container'] = [
      '#type' => 'container',
      '#markup' => $this->t('Please specify the custom endpoint values for the self-hosted version.'),
      '#states' => [
        'enabled' => [
          ':input[name="service_type"]' => ['value' => self::WSC_ON_PREMISE_SERVICE_TYPE],
        ],
        'visible' => [
          ':input[name="service_type"]' => ['value' => self::WSC_ON_PREMISE_SERVICE_TYPE],
        ],
      ],
    ];
    $onPremisesStates = [
      'required' => [
        ':input[name="service_type"]' => ['value' => self::WSC_ON_PREMISE_SERVICE_TYPE],
      ],
    ];

    $form['advanced']['on_premise_container']['service_protocol'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Protocol'),
      '#default_value' => $config->get('service_protocol') ?? '',
      '#attributes' => [
        'placeholder' => 'https',
      ],
      '#states' => $onPremisesStates,
    ];
    $form['advanced']['on_premise_container']['service_host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hostname'),
      '#default_value' => $config->get('service_host') ?? '',
      '#attributes' => [
        'placeholder' => 'localhost',
      ],
      '#states' => $onPremisesStates,
    ];
    $form['advanced']['on_premise_container']['service_port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Port'),
      '#default_value' => $config->get('service_port') ?? '',
      '#attributes' => [
        'placeholder' => '443',
      ],
      '#states' => $onPremisesStates,
    ];

    $form['advanced']['on_premise_container']['service_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service path'),
      '#default_value' => $config->get('service_path') ?? '',
      '#attributes' => [
        'placeholder' => 'virtual_directory/api',
      ],
      '#states' => $onPremisesStates,
    ];

    $form['advanced']['on_premise_container']['src_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('WProofreader script URL'),
      '#default_value' => $config->get('src_url') ?? '',
      '#attributes' => [
        'placeholder' => 'https://host_name/virtual_directory/wscbundle/wscbundle.js'
      ],
      '#states' => $onPremisesStates,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(self::WPROOFREADER_SETTINGS_ID)
      ->setData($form_state->cleanValues()->getValues())
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $serviceId = $form_state->getUserInput()['service_id'] ?? NULL;
    $form_state->clearErrors();
    $isDefaultType = $form_state->getValue('service_type') === self::WSC_DEFAULT_SERVICE_TYPE;
    if ($isDefaultType && !$serviceId) {
      $form_state->setErrorByName('service_id', $this->t('Invalid Service ID'));
    }
    if ($isDefaultType && $serviceId && !$this->webSpellCheckerHandler->isServiceIdValid($serviceId)) {
      $form_state->setErrorByName('service_id', $this->t('Invalid Service ID'));
    }

    $custom = $form_state->getUserInput()['custom'] ?? NULL;
    if (is_null(json_decode($custom))) {
      $form_state->setErrorByName('custom', $this->t('Invalid JSON format'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * Display or hide lang_code field.
   *
   * @param array $form
   *   The Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function handleServiceIdField(array &$form, FormStateInterface $form_state): AjaxResponse {
    $serviceId = $form_state->getValue('service_id');
    $response = new AjaxResponse();
    if (empty($serviceId)) {
      return $response;
    }

    $response->addCommand(new RemoveCommand('.messages-list__item'));
    if (!$this->webSpellCheckerHandler->isServiceIdValid($serviceId)) {
      $response->addCommand(new MessageCommand($this->t('Invalid Service ID'), '.messages-list__wrapper', ['type' => 'error'], TRUE));
      $response->addCommand(new CssCommand('#language-container', ['display' => 'none']));
    }
    else {
      $response->addCommand(new MessageCommand($this->t('Valid Service ID'), '.messages-list__wrapper', ['type' => 'status'], TRUE));
      $response->addCommand(new InsertCommand('#language-container', $form['language_container']));
    }
    $response->addCommand(new CssCommand('#service-id-error-container', ['display' => 'initial']));
    return $response;
  }

  /**
   * Get available languages.
   *
   * @param string $serviceId
   *   The WSC Service ID.
   *
   * @return array
   *   Array with available languages
   */
  protected function getLangOptions(string $serviceId): array {
    $availableLanguages = $this->webSpellCheckerHandler->getAvailableLanguages($serviceId);
    if (empty($availableLanguages)) {
      return [];
    }
    return $availableLanguages;
  }

}
