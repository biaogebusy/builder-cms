<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\ckeditor5_premium_features_ai\Utility\ApiAdapter;
use Drupal\ckeditor5_premium_features_ai\Service\AiConfigHandler;
use Drupal\ckeditor5_premium_features_ai\Utility\PermissionHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * CKEditor 5 AI plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class CKEditorAi extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface, CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;
  use MessengerTrait;

  /**
   * Creates the plugin instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\ckeditor5_premium_features_ai\Utility\ApiAdapter $apiAdapter
   *   The API adapter service.
   * @param \Drupal\ckeditor5_premium_features_ai\Utility\PermissionHelper $permissionHelper
   *   The AI permission helper service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match service.
   * @param \Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker $libraryVersionChecker
   *   The CKEditor 5 core library version checker service.
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ApiAdapter $apiAdapter,
    protected AiConfigHandler $aiConfigHandler,
    protected PermissionHelper $permissionHelper,
    protected RouteMatchInterface $routeMatch,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LibraryVersionChecker $libraryVersionChecker,
    protected AccountProxyInterface $currentUser,
                                     ...$parent_arguments
  ) {
    parent::__construct(...$parent_arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('config.factory'),
      $container->get('ckeditor5_premium_features_ai.api_adapter'),
      $container->get('ckeditor5_premium_features_ai.ai_config_handler'),
      $container->get('ckeditor5_premium_features_ai.permission_helper'),
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('ckeditor5_premium_features.core_library_version_checker'),
      $container->get('current_user'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'container_type' => 'sidebar',
      'customize_models' => FALSE,
      'default_model' => 'auto',
      'available_models' => [],
      'show_model_selector' => TRUE,
      'additional_context' => ['urls', 'files'],
      'translation_languages' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['container_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('AI interface type'),
      '#options' => [
        'sidebar' => $this->t('Sidebar'),
        'overlay' => $this->t('Overlay'),
      ],
      '#default_value' => $this->configuration['container_type'] ?? 'sidebar',
      '#description' => $this->t('When in Sidebar mode, the AI user interface is displayed within the editor container.<br />When in Overlay mode, the AI user interface is displayed on top of the page.')
    ];

    $form['show_model_selector'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show model selector'),
      '#default_value' => $this->configuration['show_model_selector'] ?? TRUE,
      '#description' => $this->t('Displays the model selector (or model label in case there is only one available).'),
    ];

    $form['customize_models'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Customize models available in model selector'),
      '#default_value' => $this->configuration['customize_models'] ?? FALSE,
      '#description' => $this->t('Allows to customize list of available models and change the default one. <br /> <strong>Disclaimer:</strong> Selection of available models may change (for example in case model provider does not support it anymore). Please be aware of that possibility.'),
      '#states' => [
        'visible' => [
          ':input[name="editor[settings][plugins][ckeditor5_premium_features_ai__ai][show_model_selector]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // @TODO handle compatibility version once we have more.
    $models = $this->apiAdapter->getModels("1");
    $selectedModels = array_keys($this->configuration['available_models']) ?? [];
    $options = [];

    $header = [
      'name' => 'Name',
      'provider' => 'Provider',
      'description' => 'Description',
    ];
    $rows = [];

    if (isset($models['items'])) {
      foreach ($models['items'] as $model) {
        $key = str_replace('.', '_dot_', $model['id']);
        $rows[$key] = [
          'name' => $model['name'],
          'provider' => $model['provider'],
          'description' => $model['description'],
        ];
        $options[$key] = $model['name'] . ' (' . $model['provider'] . ')';
      }
    }

    $form['available_models_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="editor[settings][plugins][ckeditor5_premium_features_ai__ai][customize_models]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['available_models_wrapper']['available_models'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#default_value' => !empty($selectedModels) ? array_combine($selectedModels, $selectedModels) : [],
      '#empty' => $this->t('No items found'),
    ];


    $form['default_model'] = [
      '#type' => 'select',
      '#title' => $this->t('Default AI Model'),
      '#default_value' => $this->configuration['default_model'] ?? '',
      '#options' => $options,
    ];

    $form['additional_context'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Additional context sources allowed in AI Chat'),
      '#options' => [
        'urls' => $this->t('URLs'),
        'files' => $this->t('Files'),
      ],
      '#default_value' => $this->configuration['additional_context'],
      '#description' => $this->t('Determines types of additional context that users may add to the conversation. The content of edited field is the default context.'),
    ];

    $form['translation_languages'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Translation languages'),
      '#default_value' => $this->configuration['translation_languages'] ?? '',
      '#description' => $this->t("Custom languages for the AI Translate tab. Enter one language per line (for example 'German')"),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Validate default model is not empty
    $values = $form_state->getValues();
    $availableModels = array_filter($values['available_models_wrapper']['available_models']);
    if (!empty($values['customize_models'])) {
      if (!in_array($values['default_model'], $availableModels)) {
        $form_state->setErrorByName('default_model', $this->t('The default model must be one of selected models.'));
      }

      // Validate available models is not empty
      if (empty($availableModels)) {
        $form_state->setErrorByName('available_models', $this->t('Available AI models cannot be empty.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['container_type'] = $form_state->getValue('container_type');
    $this->configuration['customize_models'] = $form_state->getValue('customize_models');
    $this->configuration['default_model'] = $form_state->getValue('default_model');
    $selectedModels = $form_state->getValue('available_models_wrapper');
    $this->configuration['available_models'] = array_filter($selectedModels['available_models']);
    $this->configuration['show_model_selector'] = $form_state->getValue('show_model_selector');
    // Normalize checkboxes values to a list of enabled keys.
    $additionalContext = $form_state->getValue('additional_context');
    $additionalContext = array_keys(array_filter($additionalContext));
    $this->configuration['additional_context'] = $additionalContext;
    $this->configuration['translation_languages'] = $form_state->getValue('translation_languages');
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config['ai']['container'] = [
      'type' => $this->configuration['container_type'],
      'side' => 'right',
      'visibleByDefault' => false,
    ];

    // Configure chat features based on saved configuration of additional context.
    $additionalContext = $this->configuration['additional_context'];
    $static_plugin_config['ai']['chat']['context'] = [
      'document' => ['enabled' => TRUE],
      'urls' => ['enabled' => in_array('urls', $additionalContext, TRUE)],
      'files' => ['enabled' => in_array('files', $additionalContext, TRUE)],
    ];

    $customContext = \Drupal::moduleHandler()->invokeAll('ckeditor5_premium_features_ai_context', [$editor]);

    if (\Drupal::moduleHandler()->moduleExists('ai_context')) {
      $customContext = array_merge($customContext, $this->getControlCenterContext());
    }

    if ($customContext) {
      $static_plugin_config['ai']['custom']['context'] = $customContext;
    }

    // Get the default model from configuration or use the first available model
    $defaultModel = !empty($this->configuration['default_model'])
      ? str_replace('_dot_', '.', $this->configuration['default_model'])
      : 'auto';

    // Get model selector visibility from configuration
    $model_selector_visible = !isset($this->configuration['show_model_selector']) || (bool) $this->configuration['show_model_selector'];

    $static_plugin_config['ai']['chat']['models'] = [
      'defaultModelId' => $defaultModel,
      'modelSelectorAlwaysVisible' => $model_selector_visible,
    ];

    if ($this->configuration['customize_models']) {
      // Parse available models from configuration
      $availableModels = [];
      if (!empty($this->configuration['available_models'])) {
        $availableModels = array_filter($this->configuration['available_models']);
        $availableModels = array_map(function ($value) {
          return str_replace('_dot_', '.', $value);
        }, $availableModels);
        $availableModels = array_values($availableModels);
      }

      $static_plugin_config['ai']['chat']['models']['displayedModels'] =  $availableModels;
    }

    // Build Quick Actions from saved Custom Actions config entities
    // filtered by the current text format associated with this editor.
    $static_plugin_config['ai']['quickActions']['extraCommands'] = $this->buildExtraCommands($editor);

    // Build Review Commands from saved Custom Review config entities
    // filtered by the current text format associated with this editor.
    $static_plugin_config['ai']['review']['extraCommands'] = $this->buildReviewExtraCommands($editor);

    // Build Chat Shortcuts from saved Chat Shortcut config entities
    // filtered by the current text format associated with this editor.
    $shortcuts = $this->buildChatShortcuts($editor);
    if ($shortcuts) {
      $static_plugin_config['ai']['chat']['shortcuts'] = $shortcuts;
    }
    else {
      $static_plugin_config['removePlugins'][] = 'AIChatShortcuts';
    }

    // Set custom translation languages.
    if (!empty($this->configuration['translation_languages'])) {
      $languages = str_replace(array("\r\n", "\r"), "\n", $this->configuration['translation_languages']);
      $languages = explode("\n", $languages);
      foreach ($languages as $language) {
        $static_plugin_config['ai']['reviewMode']['translations'][] = [
          'id' => strtolower(str_replace(' ', '_', $language)),
          'label' => $language,
        ];
      }
    }

    // Current user info.
    $static_plugin_config['ai']['drupalUser'] = [
      'id' => $this->currentUser->id(),
      'name' => $this->currentUser->getDisplayName(),
    ];

    // AI service URL from settings form (with default fallback).
    $static_plugin_config['ai']['serviceUrl'] = $this->aiConfigHandler->getServiceUrl();

    // Force disable the `data-id` attribute to prevent errors on change apply.
    $static_plugin_config['htmlSupport']['disallow'][] = [
      'name' => ['regexp' => ['pattern' => '/.*/']],
      'attributes' => 'data-id',
    ];

    // Adjust configuration ny user permissions. This have to be last operation
    // before returning config array.
    $permissions = $this->permissionHelper->getCKEditorAIPermissions($editor->id());
    $this->filterFunctionalityByPermissions($static_plugin_config, $permissions);

    // Adjust config array to match CKEditor version.
    $this->adjustConfigSchemaToEditorVersion($static_plugin_config);

    return $static_plugin_config;
  }

  /**
   * Build extraCommands based on Custom Action entities for the given editor.
   */
  private function buildExtraCommands(EditorInterface $editor): array {
    $format_id = $editor->id();

    // Load all custom actions and filter by allowed text formats.
    $storage = $this->entityTypeManager->getStorage('ckeditor5_ai_custom_action');
    $entities = $storage->loadMultiple();

    $commands = [];
    foreach ($entities as $entity) {
      // Each entity is \Drupal\ckeditor5_premium_features_ai\Entity\CustomAction
      $formats = (array) $entity->get('textFormats');

      // If no formats defined, treat as available everywhere; otherwise require match.
      $allowed = empty(array_filter($formats)) || in_array($format_id, $formats, TRUE);
      if (!$allowed) {
        continue;
      }

      $command = [
        'id' => (string) $entity->get('id'),
        'displayedPrompt' => (string) $entity->label(),
        'prompt' => (string) $entity->get('prompt'),
        'type' => (string) $entity->get('type'),
      ];

      $model = (string) $entity->get('model');
      if ($model !== '') {
        $command['model'] = $model;
      }
      $commands[] = $command;
    }

    return $commands;
  }

  /**
   * Build extraCommands based on Custom Review entities for the given editor.
   */
  private function buildReviewExtraCommands(EditorInterface $editor): array {
    $format_id = $editor->id();

    // Load all custom reviews and filter by allowed text formats.
    $storage = $this->entityTypeManager->getStorage('ckeditor5_ai_custom_review');
    $entities = $storage->loadMultiple();

    $commands = [];
    foreach ($entities as $entity) {
      /** @var \Drupal\ckeditor5_premium_features_ai\Entity\CustomReview $entity */
      $formats = (array) $entity->get('textFormats');

      // If no formats defined, treat as available everywhere; otherwise require match.
      $allowed = empty(array_filter($formats)) || in_array($format_id, $formats, TRUE);
      if (!$allowed) {
        continue;
      }

      $command = [
        'id' => (string) $entity->get('id'),
        'label' => (string) $entity->label(),
        'description' => (string) $entity->get('description'),
        'prompt' => (string) $entity->get('prompt'),
      ];

      $model = (string) $entity->get('model');
      if ($model !== '') {
        $command['model'] = $model;
      }
      $commands[] = $command;
    }

    return $commands;
  }

  /**
   * Build shortcuts based on Chat Shortcut entities for the given editor.
   */
  private function buildChatShortcuts(EditorInterface $editor): array {
    $isCKEditor48 = $this->libraryVersionChecker->isLibraryVersionHigherOrEqual('48.0.0');

    $format_id = $editor->id();

    // Load all chat shortcuts and filter by allowed text formats.
    $storage = $this->entityTypeManager->getStorage('ckeditor5_ai_chat_shortcut');
    $entities = $storage->loadMultiple();

    $shortcuts = [];
    foreach ($entities as $entity) {
      /** @var \Drupal\ckeditor5_premium_features_ai\Entity\ChatShortcut $entity */
      $formats = (array) $entity->get('textFormats');

      // If no formats defined, treat as available everywhere; otherwise require match.
      $allowed = empty(array_filter($formats)) || in_array($format_id, $formats, TRUE);
      if (!$allowed) {
        continue;
      }

      $shortcut = [
        'id' => (string) $entity->id(),
        'type' => (string) $entity->get('type'),
        'label' => (string) $entity->label(),
      ];

      if ($shortcut['type'] === 'chat') {
        $shortcut['prompt'] = (string) $entity->get('prompt');
        $shortcut['useReasoning'] = (bool) $entity->get('useReasoning');
        $shortcut['useWebSearch'] = (bool) $entity->get('useWebSearch');
      }
      elseif ($shortcut['type'] === 'review') {
        $key = $isCKEditor48 ? 'commandId' : 'check';
        $shortcut[$key] = (string) $entity->get('commandId');
      }

      $shortcuts[] = $shortcut;
    }

    return $shortcuts;
  }

  private function getProviderModelsMapping(): array {
    $apiInfo = $this->apiAdapter->getModels("1");
    $result = [];
    if (!(isset($apiInfo['items']) && is_array($apiInfo['items']))) {
      return $result;
    }
    foreach ($apiInfo['items'] as $item) {
      if (isset($item['provider'])) {
        $result[strtolower($item['provider'])][] = $item['id'];
      }
    }
    return $result;
  }

  /**
   * Filters AI UI functionality based on granted permissions.
   *
   * Mutates the provided CKEditor 5 static plugin config to expose only the
   * features allowed by the given CKEditor AI permissions.
   *
   * Effect on configuration:
   * - Limits `ai.chat.models.displayedModels` to the allowed set.
   * - Disables Chat entirely by adding `AIChat` to `removePlugins` when
   *   conversations are not permitted.
   * - Toggles Chat context sources (URLs / files) based on permissions.
   * - Disables Review mode (`AIReviewMode`) and/or Quick actions
   *   (`AIQuickActions`) when not permitted.
   *
   * @param array $static_plugin_config
   *   The CKEditor 5 plugin configuration to be filtered. Passed by reference
   *   and modified in place.
   * @param array $permissions
   *   A flat list of permission tokens (strings) describing allowed features.
   *   Compatible with CKEditor AI permissions format for reference see:
   *   https://ckeditor.com/docs/cs/latest/guides/ckeditor-ai/permissions.html
   *
   */
  private function filterFunctionalityByPermissions(array &$static_plugin_config, array $permissions): void {
    // Adjust available features according to permissions.
    if (in_array('ai:admin', $permissions)) {
      return ;
    }
    $removePlugins = $static_plugin_config["removePlugins"] ?? [];

    // Handle models
    $providerModels = [];
    // Do not filter models list if all are permitted
    if (!in_array('ai:models:*', $permissions)) {
      $allowedModels = [];
      foreach ($this->arrayFilterContains('ai:models', $permissions) as $value) {
        $elements = explode(':', $value);
        if (end($elements) === 'agent') {
          $allowedModels[] = 'agent-1';
        }
        elseif (end($elements) === '*') {
          $provider = prev($elements);
          $providerModels = empty($providerModels) ? $this->getProviderModelsMapping() : $providerModels;
          $allowedModels = array_merge($allowedModels, $providerModels[$provider]);
        }
        else {
          $allowedModels[] = end($elements);
        }
      }
      if (isset($static_plugin_config["ai"]["chat"]["models"]["displayedModels"])) {
        $displayedModels = $static_plugin_config["ai"]["chat"]["models"]["displayedModels"];
        $static_plugin_config["ai"]["chat"]["models"]["displayedModels"] = array_values(array_intersect($allowedModels, $displayedModels));
      }
      else {
        $static_plugin_config["ai"]["chat"]["models"]["displayedModels"] = $allowedModels;
      }
    }

    // Handle chat
    if (!$this->arrayContains('ai:conversations', $permissions)) {
      $removePlugins[] = 'AIChat';
    }
    else {
      // Handle contexts
      $allContext = in_array('ai:conversations:*', $permissions) || in_array('ai:conversations:context:*', $permissions);
      if (isset($static_plugin_config["ai"]["chat"]["context"]["urls"]["enabled"]) && $static_plugin_config["ai"]["chat"]["context"]["urls"]["enabled"]) {
        $urlsGranted = FALSE;
        if ($allContext || in_array('ai:conversations:context:urls', $permissions)) {
          $urlsGranted = TRUE;
        }
        $static_plugin_config["ai"]["chat"]["context"]["urls"]["enabled"] = $urlsGranted;
      }
      if (isset($static_plugin_config["ai"]["chat"]["context"]["files"]["enabled"]) && $static_plugin_config["ai"]["chat"]["context"]["files"]["enabled"]) {
        $filesGranted = FALSE;
        if ($allContext || $this->arrayContains('ai:conversations:context:files', $permissions)) {
          $filesGranted = TRUE;
        }
        $static_plugin_config["ai"]["chat"]["context"]["files"]["enabled"] = $filesGranted;
      }
    }

    // Handle reviews
    if (!$this->arrayContains('ai:reviews', $permissions)) {
      $removePlugins[] = 'AIReviewMode';
    }

    // Handle quick actions
    if (!$this->arrayContains('ai:actions', $permissions)) {
      $removePlugins[] = 'AIQuickActions';
    }

    if ($removePlugins) {
      $static_plugin_config["removePlugins"] = $removePlugins;
    }
  }

  private function adjustConfigSchemaToEditorVersion(&$config): void {
    if ($this->libraryVersionChecker->isLibraryVersionHigherOrEqual('47.5.0')) {
      if (isset($config['ai']['chat']['models'])) {
        $config['ai']['models'] = $config['ai']['chat']['models'];
        unset($config['ai']['chat']['models']);
        $config['ai']['models']['showModelSelector'] = $config['ai']['models']['modelSelectorAlwaysVisible'];
        unset($config['ai']['models']['modelSelectorAlwaysVisible']);
      }
      if (isset($config['ai']['reviewMode']['translations'])) {
        $config['ai']['translate']['languages'] = $config['ai']['reviewMode']['translations'];
        unset($config['ai']['reviewMode']);
      }
    }
  }

  /**
   * Checks whether any value in an array contains the given substring.
   *
   * @param string $needle
   *   The substring to search for.
   * @param array $haystack
   *   The array of values to search in.
   *
   * @return string|bool
   *   Value if any array value contains the substring, FALSE otherwise.
   */
  private function arrayContains(string $needle, array $haystack): mixed {
    foreach ($haystack as $value) {
      if (str_contains($value, $needle)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns all array values that contain the given substring.
   *
   * @param string $needle
   *   The substring to search for.
   * @param array $haystack
   *   The array of values to search in.
   *
   * @return string[]
   *   A list of values that contain the substring.
   */
  private function arrayFilterContains(string $needle, array $haystack): array {
    $result = [];

    foreach ($haystack as $value) {
      if (str_contains($value, $needle)) {
        $result[] = $value;
      }
    }

    return $result;
  }

  /**
   * Gets matching context defined in the Context Control Center module.
   */
  private function getControlCenterContext(): array {
    $selector = \Drupal::service('ai_context.selector');
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $request = new \Drupal\ai_context\Model\AiContextRequest(
      task: 'Global context to be added to the CKEditor AI configuration',
      scopeSubscriptions: ['language' => [$language]],
      alwaysInclude: [],
      neverInclude: [],
    );

    $result = $selector->select($request);
    $items = $result->getSelectedItems(TRUE);

    $customContext = [];
    foreach ($items as $key => $item) {
      $customContext[] = [
        'id' => $key,
        'type' => 'text',
        'label' => $item['label'],
        'data' => [
          'content' => $item['purpose'] . "\r\n" . $item['content_snippet'],
          'type' => 'markdown',
        ],
      ];
    }

    return $customContext;
  }

}
