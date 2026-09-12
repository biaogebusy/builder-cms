<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_wproofreader\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features_wproofreader\Form\SettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * WProofreader ckeditor5 plugin class.
 */
class WProofreader extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * WProofreader config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $wProofReaderConfig;
  /**
   * Host name.
   *
   * @var string
   */
  private string $host;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              ConfigFactoryInterface $configFactory,
                              RequestStack $request,
                              protected UrlGeneratorInterface $urlGenerator,
                              protected AccountProxyInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->wProofReaderConfig = $configFactory->get(SettingsForm::WPROOFREADER_SETTINGS_ID);
    $this->host = $request->getCurrentRequest()->getHost();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('request_stack'),
      $container->get('url_generator'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config = parent::getDynamicPluginConfig($static_plugin_config, $editor);
    $static_plugin_config['wproofreader']['lang'] = $this->wProofReaderConfig->get('lang_code') ?? 'auto';

    $serviceType = $this->wProofReaderConfig->get('service_type') ?? SettingsForm::WSC_DEFAULT_SERVICE_TYPE;
    $userPermission = $this->currentUser->hasPermission('ckeditor5 webspellchecker proxy access');

    $static_plugin_config['wproofreader']['cke5']['serviceType'] = $serviceType;
    $static_plugin_config['wproofreader']['cke5']['validPermission'] = $userPermission;

    if ($serviceType === SettingsForm::WSC_ON_PREMISE_SERVICE_TYPE) {
      $static_plugin_config['wproofreader']['serviceProtocol'] = $this->wProofReaderConfig->get('service_protocol') ?? '';
      $static_plugin_config['wproofreader']['serviceHost'] = $this->wProofReaderConfig->get('service_host') ?? '';
      $static_plugin_config['wproofreader']['servicePort'] = $this->wProofReaderConfig->get('service_port') ?? '';
      $static_plugin_config['wproofreader']['servicePath'] = $this->wProofReaderConfig->get('service_path') ?? '';
      $static_plugin_config['wproofreader']['srcUrl'] = $this->wProofReaderConfig->get('src_url') ?? '';
    }
    else {
      $static_plugin_config['wproofreader']['srcUrl'] = SettingsForm::DEFAULT_WSCBUNDLE_URL;
      $static_plugin_config['wproofreader']['serviceHost'] = $this->host;
      $static_plugin_config['wproofreader']['servicePath'] = $this->urlGenerator->generateFromRoute('ckeditor5_premium_features_wproofreader.webspellchecker_proxy');
    }

    // Default settings
    $rawConfig = $this->wProofReaderConfig->getRawData();
    $static_plugin_config['wproofreader']['spellingSuggestions'] = isset($rawConfig['spellingSuggestions']) ? (bool) $rawConfig['spellingSuggestions'] : TRUE;
    $static_plugin_config['wproofreader']['grammarSuggestions'] = isset($rawConfig['grammarSuggestions']) ? (bool) $rawConfig['grammarSuggestions'] : TRUE;
    $static_plugin_config['wproofreader']['styleGuideSuggestions'] = isset($rawConfig['styleGuideSuggestions']) ? (bool) $rawConfig['styleGuideSuggestions'] : TRUE;
    $static_plugin_config['wproofreader']['autocorrect'] = isset($rawConfig['autocorrect']) ? (bool) $rawConfig['autocorrect'] : TRUE;
    $static_plugin_config['wproofreader']['autocomplete'] = isset($rawConfig['autocomplete']) ? (bool) $rawConfig['autocomplete'] : FALSE;
    $static_plugin_config['wproofreader']['ignoreAllCapsWords'] = isset($rawConfig['ignoreAllCapsWords']) ? (bool) $rawConfig['ignoreAllCapsWords'] : FALSE;
    $static_plugin_config['wproofreader']['ignoreDomainNames'] = isset($rawConfig['ignoreDomainNames']) ? (bool) $rawConfig['ignoreDomainNames'] : TRUE;
    $static_plugin_config['wproofreader']['ignoreWordsWithMixedCases'] = isset($rawConfig['ignoreWordsWithMixedCases']) ? (bool) $rawConfig['ignoreWordsWithMixedCases'] : FALSE;
    $static_plugin_config['wproofreader']['ignoreWordsWithNumbers'] = isset($rawConfig['ignoreWordsWithNumbers']) ? (bool) $rawConfig['ignoreWordsWithNumbers'] : TRUE;
    $static_plugin_config['wproofreader']['customDictionaryIds'] = isset($rawConfig['company_dictionaries']) ? $rawConfig['company_dictionaries'] : '';

    // User settings sections access.
    $settingsSections = [];
    $disableOptionsStorage = [];
    $actionItems = ['proofreadDialog'];
    if ($this->currentUser->hasPermission('ckeditor5 wproofreader user dictionary')) {
      $settingsSections[] = 'dictionaries';
    }
    if ($this->currentUser->hasPermission('ckeditor5 wproofreader add word')) {
      $actionItems[] = 'addWord';
    }
    if ($this->currentUser->hasPermission('ckeditor5 wproofreader ignore all')) {
      $actionItems[] = 'ignoreAll';
    }
    if ($this->currentUser->hasPermission('ckeditor5 wproofreader user language')) {
      $settingsSections[] = 'languages';
    }
    else {
      $disableOptionsStorage[] = 'lang';
    }
    if ($this->currentUser->hasPermission('ckeditor5 wproofreader user general settings')) {
      $settingsSections[] = 'general';
    }
    else {
      $disableGeneralOptionsStorage = ['spellingSuggestions', 'grammarSuggestions', 'styleGuideSuggestions', 'autocorrect', 'autocomplete'];
      $disableOptionsStorage = array_merge($disableOptionsStorage, $disableGeneralOptionsStorage);
    }
    if ($this->currentUser->hasPermission('ckeditor5 wproofreader user ignore settings')) {
      $settingsSections[] = 'options';
    }
    else {
      $disableIgnoreOptionsStorage = ['ignoreAllCapsWords', 'ignoreDomainNames', 'ignoreWordsWithMixedCases', 'ignoreWordsWithNumbers'];
      $disableOptionsStorage = array_merge($disableOptionsStorage, $disableIgnoreOptionsStorage);
    }

    if ($this->currentUser->hasPermission('ckeditor5 wproofreader toggle proofreading')) {
      $actionItems[] = 'toggle';
    }
    if ($settingsSections) {
      $static_plugin_config['wproofreader']['settingsSections'] = $settingsSections;
      $actionItems[] = 'settings';
    }

    $actionItems[] = 'report';
    $static_plugin_config['wproofreader']['actionItems'] = $actionItems;

    if ($disableOptionsStorage) {
      $static_plugin_config['wproofreader']['disableOptionsStorage'] = $disableOptionsStorage;
    }

    $static_plugin_config['wproofreader']['aiWritingAssistant'] = (bool)$this->wProofReaderConfig->get('aiWritingAssistant') ?? FALSE;
    $customConfigRaw = $this->wProofReaderConfig->get('custom') ?? '';
    $customConfig = json_decode($customConfigRaw, TRUE);
    if ($customConfig) {
      $static_plugin_config['wproofreader'] = array_merge($static_plugin_config['wproofreader'], $customConfig);
    }

    return $static_plugin_config;
  }

}
