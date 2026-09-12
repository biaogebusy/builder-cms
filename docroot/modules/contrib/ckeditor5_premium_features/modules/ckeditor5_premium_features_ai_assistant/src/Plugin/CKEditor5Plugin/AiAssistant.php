<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\ckeditor5_premium_features_ai_assistant\AITextAdapter;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 "AI Assistant" plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class AiAssistant extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface, CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;
  use MessengerTrait;

  /**
   * Creates the plugin instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker $versionChecker
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(
                              protected ConfigFactoryInterface $configFactory,
                              protected LibraryVersionChecker $versionChecker,
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
      $container->get('ckeditor5_premium_features.core_library_version_checker'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config = parent::getDynamicPluginConfig($static_plugin_config, $editor);
    $config = $this->configFactory->get('ckeditor5_premium_features_ai_assistant.settings');
    $removeCommands = $this->configuration['remove_commands'] ?? [];

    $providerName = $config->get('ai_provider');

    if (!$providerName) {
      // If no provider is selected, disable the AI Assistant plugin.
      $static_plugin_config['removePlugins'] = ['AWSTextAdapter', 'OpenAITextAdapter', 'AIAssistant', 'AIServiceAdapter'];

      // Display a warning message to the user.
      $this->messenger();
      $this->messenger->addWarning($this->t('AI Assistant plugin is disabled because no AI provider is selected. Please configure an AI provider in the AI Assistant <a href="/admin/config/ckeditor5-premium-features/ai-assistant">settings page</a>.'));

      return $static_plugin_config;
    }

    $textAdapter = $config->get('textAdapter') ?? NULL;
    $static_plugin_config['ai']['textAdapter'] = $textAdapter;
    $textAdapterPlugin = '';

    if ($config->get('use_custom_endpoint') && $apiUrl = $config->get('api_url')) {
      $static_plugin_config['ai'][$textAdapter]['apiUrl'] = $apiUrl;
      if ($authKey = $config->get('auth_key')) {
        $static_plugin_config['ai'][$textAdapter]['requestHeaders']['Authorization'] = "Bearer: {$authKey}";
      }
    }
    else {
      $static_plugin_config['ai'][$textAdapter]['apiUrl'] = Url::fromRoute('ckeditor5_premium_features_ai_assistant.ai_assistant_proxy_provider')
        ->toString();
    }

    $providerName = $config->get('ai_provider');
    $pluginManager = \Drupal::service('plugin.manager.ckeditor5_ai_provider');
    $providerPlugin = $pluginManager->getDefinition($providerName);
    $class = $providerPlugin['class'] ?? NULL;
    $providerLabel = $providerPlugin['label']->render();
    $isInstalled = $class::isInstalled('AI Assistant plugin');

    //Disable AI Assistant plugin in case the provider is not installed.
    if (!$isInstalled) {
      $static_plugin_config['removePlugins'] = $this->getUnnecessaryTextAdapterPlugins($textAdapterPlugin);
      $static_plugin_config['removePlugins'][] = 'AIAssistant';
      $static_plugin_config['removePlugins'][] = 'AIServiceAdapter';
      unset($static_plugin_config['ai']);
      return $static_plugin_config;
    }

    if ($textAdapter === AITextAdapter::AWS->value) {
      $model = $config->get("{$providerName}_model");
      $static_plugin_config['ai'][$textAdapter]['requestParameters'] = [
        'model' => $model,
        'stream' => FALSE,
      ];
      $textAdapterPlugin = AITextAdapter::getAITextAdapterPluginName(AITextAdapter::AWS);
    }
    elseif ($textAdapter === AITextAdapter::OpenAI->value) {
      $model = !empty($config->get("{$providerName}_model")) ? $config->get("{$providerName}_model") : 'gpt-3.5-turbo';
      $parameters = json_decode($config->get("{$providerName}_parameters") ?? '', TRUE) ?? [];
      $defaults = [
        'model' => $model,
        'max_tokens' => 2000,
        'temperature' => 1,
        'top_p' => 1,
        'stream' => TRUE,
      ];
      $requestParameters = array_merge($defaults, $parameters);
      $static_plugin_config['ai'][$textAdapter]['requestParameters'] = $requestParameters;
      $textAdapterPlugin = AITextAdapter::getAITextAdapterPluginName(AITextAdapter::OpenAI);
    }

    if ($config->get('disable_default_styles')) {
      $static_plugin_config['ai']['useTheme'] = FALSE;
    }

    if (!empty($removeCommands)) {
      $static_plugin_config['ai']['aiAssistant']['removeCommands'] = $removeCommands;
    }
    $extraCommandsGroups = $this->getAvailableCommandsGroups($editor);
    if (!empty($extraCommandsGroups)) {
      $static_plugin_config['ai']['aiAssistant']['extraCommandGroups'] = [];
      foreach ($extraCommandsGroups as $group) {
        $static_plugin_config['ai']['aiAssistant']['extraCommandGroups'][] = [
          'groupId' => $group['id'],
          'groupLabel' => $group['label'],
          'commands' => $group['commands'],
        ];
      }
    }

    if ($this->versionChecker->isLibraryVersionHigherOrEqual('47.0.0')) {
      $static_plugin_config['ai'] = $this->convertSettingsToNewFormat($static_plugin_config['ai']);
    }

    $static_plugin_config['removePlugins'] = $this->getUnnecessaryTextAdapterPlugins($textAdapterPlugin);
    return $static_plugin_config;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'remove_commands' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['remove_commands'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Remove provided commands'),
      '#default_value' => implode("\n", $this->configuration['remove_commands']),
      '#description' => $this->t(
        'A list of command IDs to be removed from the "AI commands" plugin. Enter one or more ids in each line </br>
           You can find the list of default plugins <a href=":documentation_url">here</a>.',
        [':documentation_url' => 'https://ckeditor.com/docs/ckeditor5/latest/api/module_ai_aiassistant-AIAssistantConfig.html#member-commands']),
      '#ajax' => FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $form_value = $form_state->getValue('remove_commands');
    $lines = explode("\n", $form_value);
    $val = array_map(fn($item) => rtrim($item), $lines);
    $form_state->setValue('remove_commands', $val);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['remove_commands'] = $form_state->getValue('remove_commands');
  }

  /**
   * Returns array of CKEditor5 AI Assistant Commands Group.
   *
   * @param \Drupal\editor\EditorInterface $editor
   *   Editor.
   *
   * @return array
   *   An Array of CKEditor5 AI Assistant Commands Group.
   */
  protected function getAvailableCommandsGroups(EditorInterface $editor): array {
    $format = $editor->getFilterFormat()->id();

    $entityStorage = \Drupal::service('entity_type.manager')
      ->getStorage('ckeditor5_ai_command_group');
    $query = $entityStorage->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('status', TRUE);
    $query->condition('textFormats.*', $format, '=');
    $query->sort('weight');
    $results = $query->execute();

    $commandsGroups = $entityStorage->loadMultiple($results);
    $definitions = [];
    foreach ($commandsGroups as $commandGroup) {
      if (!empty($commandGroup->get('commands'))) {
        $definition = $commandGroup->getDefinition();
        $definitions[] = $this->convertGroupDefinitionToConfig($definition);
      }
    }

    return $definitions;
  }

  /**
   * Get array of Text Adapter plugins to disable.
   *
   * @param string $activeTextAdapter
   *  Text Adapter plugin for active AI provider
   * @return array
   *  Text Adapter plugins that should be disabled.
   */
  private function getUnnecessaryTextAdapterPlugins(string $activeTextAdapter): array {
    $allAdapters = ['AWSTextAdapter', 'OpenAITextAdapter'];
    return array_diff($allAdapters, [$activeTextAdapter]);
  }

  /**
   * Convert CKEditor5 AI Command Group definition to config that can be passed to the editor.
   *
   * @param array $groupDefinition
   *   CKEditor5 AI Command Group definition.
   *
   * @return array
   *   CKEditor5 AI Command Group config.
   */
  private function convertGroupDefinitionToConfig(array $groupDefinition): array {
    $output = [];
    $output['id'] = $groupDefinition['id'];
    $output['label'] = $groupDefinition['label'];
    $output['commands'] = [];
    foreach ($groupDefinition['commands'] as $command) {
      $output['commands'][] = [
        'id' => $command['command_id'],
        'label' => $command['label'],
        'prompt' => $command['prompt'],
      ];
    }
    return $output;
  }

  /**
   * Converts the settings array to format used in CKEditor v47.0.0 and newer.
   *
   * @param array $oldSettings
   *   Old settings format.
   *
   * @return array
   *   New settings format.
   */
  private function convertSettingsToNewFormat(array $oldSettings): array {
    $newSettings = [];

    $newSettings['assistant'] = $oldSettings['aiAssistant'] ?? [];
    $newSettings['assistant']['textAdapter'] = $oldSettings['textAdapter'];
    if (isset($oldSettings['aws'])) {
      $newSettings['assistant']['adapter']['aws'] = $oldSettings['aws'];
    }
    if (isset($oldSettings['openAI'])) {
      $newSettings['assistant']['adapter']['openAI'] = $oldSettings['openAI'];
    }
    if (isset($oldSettings['useTheme'])) {
      $newSettings['assistant']['useTheme'] = $oldSettings['useTheme'];
    }

    return $newSettings;
  }

}
