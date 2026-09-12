<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Plugin\CKEditor5AiProvider;

use Drupal\ckeditor5_premium_features_ai_assistant\AITextAdapter;
use Drupal\ckeditor5_premium_features_ai_assistant\CKEditor5AiProviderPluginBase;
use Drupal\ckeditor5_premium_features_ai_assistant\Form\SettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use OpenAI\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plugin implementation of the ckeditor5_ai_provider.
 *
 * @CKEditor5AiProvider(
 *   id = "openai_service",
 *   label = @Translation("OpenAI Service"),
 *   description = @Translation("OpenAI Service Provider."),
 * )
 */
final class OpenAi extends CKEditor5AiProviderPluginBase {

  use StringTranslationTrait;
  use OpenAITrait;

  /**
   * Config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $config;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $configFactory->get(SettingsForm::AI_ASSISTANT_SETTINGS_ID);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processRequest(Request $request): Response {
    if (!$this->getAuthKey()) {
      return new Response('Missing AI service configuration.', 503);
    }
    $content = $request->getContent();
    $requestData = json_decode($content, TRUE);
    if ($requestData['stream']) {
      return $this->processStreamed($requestData);
    }
    return $this->processRegular($requestData);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigFields(): array {
    $fields = [];
    $default = [];
    if (!self::isInstalled('OpenAI provider')) {
      $default['#disabled'] = TRUE;
    }

    $fields['auth_key'] = [
      "#type" => "textarea",
      "#title" => $this->t("Auth key"),
      "#required" => TRUE,
    ] + $default;

    return array_merge($fields, $this->getParametersFields($default));
  }

  /**
   * Returns OpenAI client.
   *
   * @return \OpenAI\Client
   *   Client object.
   */
  private function getClient(): Client {
    return \OpenAI::client($this->getAuthKey());
  }

  /**
   * Returns auth key for the client.
   *
   * @return string
   *   The auth key.
   */
  private function getAuthKey(): string {
    return $this->config->get($this->getPluginId() . '_' . 'auth_key') ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTextAdapter(): AITextAdapter {
    return AITextAdapter::OpenAI;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->pluginDefinition['description'];
  }

}
