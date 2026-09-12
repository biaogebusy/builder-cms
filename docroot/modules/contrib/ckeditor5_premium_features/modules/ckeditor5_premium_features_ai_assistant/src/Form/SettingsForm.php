<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Form;

use Drupal\ckeditor5_premium_features_ai_assistant\Utility\AiAssistantHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the configuration form of the "AI Assistant" feature.
 */
class SettingsForm extends ConfigFormBase {

  const AI_ASSISTANT_SETTINGS_ID = 'ckeditor5_premium_features_ai_assistant.settings';

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected AiAssistantHelper $aiAssistantHelper,
    protected $messenger
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('ckeditor5_premium_features_ai_assistant.ai_assistant_helper'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor5_premium_features_ai_assistant_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      self::AI_ASSISTANT_SETTINGS_ID,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state):array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config(self::AI_ASSISTANT_SETTINGS_ID);
    $providers = $this->aiAssistantHelper->getAllProviders();
    $provider = $config->get('ai_provider') ?? AiAssistantHelper::DEFAULT_PROVIDER;

    if ($form_state->isRebuilding()) {
      $provider = $form_state->getValue('ai_provider');
    }
    $form['provider_settings'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'provider-settings'],
    ];

    // Add messages container to the form element that will be replaced.
    $messages = $this->messenger->all();
    $this->messenger->deleteAll();
    $form['provider_settings']['messages'] = [
      '#theme' => 'status_messages',
      '#message_list' => $messages,
      '#status_headings' => [
        'status' => $this->t('Status message'),
        'error' => $this->t('Error message'),
        'warning' => $this->t('Warning message'),
      ],
      '#attributes' => ['class' => ['messages', 'messages--status']],
    ];

    $providerDescription = $this->aiAssistantHelper->getProviderDescription($provider);
    $form['provider_settings']['ai_provider'] = [
      '#type' => 'select',
      '#options' => $providers,
      '#title' => $this->t('AI provider'),
      '#required' => TRUE,
      '#default_value' => $provider,
      '#description' => $providerDescription,
      '#ajax' => [
        'callback' => '::changeProviderFields',
        'wrapper' => 'provider-settings',
        'method' => 'replaceWith',
      ],
    ];

    $providerFields = $this->aiAssistantHelper->getProviderFormFields($provider);
    foreach ($providerFields as $key => $field) {
      if (!isset($field['#default_value'])) {
        $field['#default_value'] = $config->get($key);
      }
      $form['provider_settings'][$key] = $field;
    }

    $form['provider_settings']['textAdapter'] = [
      '#type' => 'textfield',
      '#default_value' => $this->aiAssistantHelper->getProviderTextAdapter($provider),
      '#disabled' => TRUE,
      '#attributes' => ['style' => 'display: none;'],
    ];
    $form['disable_default_styles'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Disable the feature's default theme"),
      '#required' => FALSE,
      '#description' => $this->t('If you do not want default styling, you can disable it.'),
      '#default_value' => $config->get('disable_default_styles'),
    ];
    $form['manage_commands_groups'] = [
      '#type' => 'details',
      '#title' => $this->t('Manage commands groups'),
      '#open' => FALSE,
      '#description' => $this->t('You can add extra AI Commands to AI Assistant'),
    ];
    $form['manage_commands_groups']['go_to_manage'] = [
      '#type' => 'submit',
      '#value' => $this->t('Commands group list'),
      '#submit' => ['::manageCommands'],
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => (bool) $config->get('use_custom_endpoint'),
      '#description' =>
      $this->t('If you want to use your custom proxy, provide the URL and Auth key for the endpoint.'),
    ];
    $form['advanced']['use_custom_endpoint'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Use custom proxy endpoint"),
      '#required' => FALSE,
      '#default_value' => $config->get('use_custom_endpoint'),
    ];
    $form['advanced']['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Url'),
      '#description' => $this->t('The URL to the custom proxy endpoint.'),
      '#default_value' => $config->get('api_url'),
      '#states' => [
        'disabled' => [
          ':input[name="use_custom_endpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['advanced']['auth_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auth Key'),
      '#description' => $this->t('The auth key for your endpoint. <b>This key will be visible in editor config</b>.'),
      '#default_value' => $config->get('auth_key'),
      '#states' => [
        'disabled' => [
          ':input[name="use_custom_endpoint"]' => ['checked' => FALSE],
        ],
      ],
    ];
    return $form;
  }

  /**
   * Redirect to AI Command group collection.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function manageCommands(array $form, FormStateInterface $form_state):void {
    $form_state->setRedirect('entity.ckeditor5_ai_command_group.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(self::AI_ASSISTANT_SETTINGS_ID)
      ->setData($form_state->cleanValues()->getValues())
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Callback for changing provider.
   *
   * @param array $form
   *   The form.
   * @param FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return AjaxResponse
   */
  public function changeProviderFields(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Add messages to the form element that will be replaced
    $messages = $this->messenger->all();
    $this->messenger->deleteAll();
    if (!empty($messages)) {
      // Ensure messages are rendered with HTML
      foreach ($messages as $type => $type_messages) {
        foreach ($type_messages as $key => $message) {
          if (is_string($message)) {
            $messages[$type][$key] = ['#markup' => $message];
          }
        }
      }

      $form['provider_settings']['messages'] = [
        '#theme' => 'status_messages',
        '#message_list' => $messages,
        '#status_headings' => [
          'status' => $this->t('Status message'),
          'error' => $this->t('Error message'),
          'warning' => $this->t('Warning message'),
        ],
        '#attributes' => ['class' => ['messages', 'messages--status']],
      ];
    }

    // Replace the provider settings container
    $response->addCommand(new \Drupal\Core\Ajax\ReplaceCommand('#provider-settings', $form['provider_settings']));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->isSubmitted()) {
      $providerId = $form_state->getValue('ai_provider');
      $provider = $this->aiAssistantHelper->getProviderById($providerId);
      $provider->validateFields($form_state);
    }
    parent::validateForm($form, $form_state);
  }

}
