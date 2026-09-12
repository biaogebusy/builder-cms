<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Utility;

use Drupal\ckeditor5_premium_features\CKeditorPremiumLoggerChannelTrait;
use Drupal\ckeditor5_premium_features_ai_assistant\CKEditor5AiProviderInterface;
use Drupal\ckeditor5_premium_features_ai_assistant\CKEditor5AiProviderPluginManager;
use Drupal\ckeditor5_premium_features_ai_assistant\Form\SettingsForm;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Helper for CKEditor5 AI Assistant service providers.
 */
class AiAssistantHelper {

  use CKeditorPremiumLoggerChannelTrait;

  const DEFAULT_PROVIDER = 'openai_service';

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $aiAssistantSettings;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory, private CKEditor5AiProviderPluginManager $aiProviderPluginManager) {
    $this->aiAssistantSettings = $configFactory->get(SettingsForm::AI_ASSISTANT_SETTINGS_ID);
  }

  /**
   * Returns all available providers as a key value array.
   *
   * @return array
   */
  public function getAllProviders(): array {
    $providers = $this->aiProviderPluginManager->getAllProviders();
    return array_map(fn($x) => (string) $x['label'], $providers);
  }

  /**
   * Returns current enabled AI provider.
   *
   * @return \Drupal\ckeditor5_premium_features_ai_assistant\CKEditor5AiProviderInterface|null
   *   CKEditor5AiProviderInterface object or null.
   */
  public function getProvider(): ?CKEditor5AiProviderInterface {
    $enabledProvider = $this->aiAssistantSettings->get('ai_provider') ?? self::DEFAULT_PROVIDER;

    try {
      /**
       * @var \Drupal\ckeditor5_premium_features_ai_assistant\CKEditor5AiProviderInterface $provider
       */
      $provider = $this->aiProviderPluginManager->createInstance($enabledProvider);
    }
    catch (PluginException $exception) {
      $this->logException($exception->getMessage(), $exception);
      return NULL;
    }

    return $provider;
  }

  /**
   * Returns AI provider object if exists.
   *
   * @param string $providerId
   *   ID of the provider.
   *
   * @return \Drupal\ckeditor5_premium_features_ai_assistant\CKEditor5AiProviderInterface|null
   *   CKEditor5AiProviderInterface object or null.
   */
  public function getProviderById(string $providerId): ?CKEditor5AiProviderInterface {
    try {
      /**
       * @var \Drupal\ckeditor5_premium_features_ai_assistant\CKEditor5AiProviderInterface $provider
       */
      $provider = $this->aiProviderPluginManager->createInstance($providerId);
    }
    catch (PluginException $exception) {
      $this->logException($exception->getMessage(), $exception);
      return NULL;
    }
    return $provider;
  }

  /**
   * Returns array of provider config fields.
   *
   * @param string $providerId
   *   The Provider ID.
   *
   * @return array
   *   Array of fields.
   */
  public function getProviderFormFields(string $providerId): array {
    $provider = $this->getProviderById($providerId);
    if (!$provider) {
      return [];
    }
    $configFields = $provider->getConfigFields();
    $fields = [];
    foreach ($configFields as $key => $value) {
      $fields["{$providerId}_{$key}"] = $value;
    }
    return $fields;
  }

  /**
   * Returns the AITextAdapter name.
   *
   * @param string $providerId
   *   The Provider ID.
   *
   * @return string
   *   AITextAdapter name.
   */
  public function getProviderTextAdapter(string $providerId): string {
    $provider = $this->getProviderById($providerId);
    return $provider->getTextAdapter()->value;
  }

  /**
   * Returns the provider description.
   *
   * @param string $providerId
   *   The Provider ID.
   *
   * @return string|TranslatableMarkup
   *   Provider description.
   */
  public function getProviderDescription(string $providerId): string|TranslatableMarkup {
    $provider = $this->getProviderById($providerId);
    return $provider->getDescription();
  }

}
