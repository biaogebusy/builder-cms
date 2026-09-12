<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Plugin\CKEditor5AiProvider;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Credentials\Credentials;
use Aws\Result;
use Drupal\ckeditor5_premium_features\CKeditorPremiumLoggerChannelTrait;
use Drupal\ckeditor5_premium_features_ai_assistant\AITextAdapter;
use Drupal\ckeditor5_premium_features_ai_assistant\CKEditor5AiProviderPluginBase;
use Drupal\ckeditor5_premium_features_ai_assistant\Form\SettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plugin implementation of the ckeditor5_ai_provider.
 *
 * @CKEditor5AiProvider(
 *   id = "aws_bedrockai_service",
 *   label = @Translation("AWS BedrockAI Service"),
 *   description = @Translation("AWS BedrockAI Service Provider."),
 * )
 */
final class AWSBedrockAi extends CKEditor5AiProviderPluginBase {

  use StringTranslationTrait;
  use CKeditorPremiumLoggerChannelTrait;
  const AWS_REGIONS = [
    'us-east-2' => 'US East (Ohio) [us-east-2]',
    'us-east-1' => 'US East (N. Virginia) [us-east-1]',
    'us-west-1' => 'US West (N. California) [us-west-1]',
    'us-west-2' => 'US West (Oregon) [us-west-2]',
    'af-south-1' => 'Africa (Cape Town) [af-south-1]',
    'ap-east-1' => 'Asia Pacific (Hong Kong) [ap-east-1]',
    'ap-south-2' => 'Asia Pacific (Hyderabad) [ap-south-2]',
    'ap-southeast-3' => 'Asia Pacific (Jakarta) [ap-southeast-3]',
    'ap-southeast-4' => 'Asia Pacific (Melbourne) [ap-southeast-4]',
    'ap-south-1' => 'Asia Pacific (Mumbai) [ap-south-1]',
    'ap-northeast-3' => 'Asia Pacific (Osaka) [ap-northeast-3]',
    'ap-northeast-2' => 'Asia Pacific (Seoul) [ap-northeast-2]',
    'ap-southeast-1' => 'Asia Pacific (Singapore) [ap-southeast-1]',
    'ap-southeast-2' => 'Asia Pacific (Sydney) [ap-southeast-2]',
    'ap-northeast-1' => 'Asia Pacific (Tokyo) [ap-northeast-1]',
    'ca-central-1' => 'Canada (Central) [ca-central-1]',
    'ca-west-1' => 'Canada West (Calgary) [ca-west-1]',
    'eu-central-1' => 'Europe (Frankfurt) [eu-central-1]',
    'eu-west-1' => 'Europe (Ireland) [eu-west-1]',
    'eu-west-2' => 'Europe (London) [eu-west-2]',
    'eu-south-1' => 'Europe (Milan) [eu-south-1]',
    'eu-west-3' => 'Europe (Paris) [eu-west-3]',
    'eu-south-2' => 'Europe (Spain) [eu-south-2]',
    'eu-north-1' => 'Europe (Stockholm) [eu-north-1]',
    'eu-central-2' => 'Europe (Zurich) [eu-central-2]',
    'il-central-1' => 'Israel (Tel Aviv) [il-central-1]',
    'me-south-1' => 'Middle East (Bahrain) [me-south-1]',
    'me-central-1' => 'Middle East (UAE) [me-central-1]',
    'sa-east-1' => 'South America (São Paulo) [sa-east-1]',
    'us-gov-east-1' => 'AWS GovCloud (US-East) [us-gov-east-1]',
    'us-gov-west-1' => 'AWS GovCloud (US-West) [us-gov-west-1]',
  ];

  /**
   * @var string
   */
  private string $claudeRegex = '/^anthropic\.claude/';

  /**
   * @var string
   */
  private string $ai21Regex = '/^ai21\.j2/';

  /**
   * @var string
   */
  private string $cohereRegex = '/^cohere\.command/';

  /**
   * @var string
   */
  private string $metaRegex = '/^meta\.llama/';

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
    if (!$this->getApiSecret() || !$this->getApiKey() || !$this->getRegion() || !$this->getModel()) {
      return new Response('Missing AI service configuration.', 503);
    }
    $content = $request->getContent();
    $requestData = json_decode($content, TRUE);
    if (empty($requestData['prompt'])) {
      return new Response('Missing prompt parameter.', 400);
    }
    $modelResponse = $this->invokeModel($this->getModel(), $requestData['prompt']);
    if (!$modelResponse) {
      return new Response('Error while invoking AWS Model. Check model configuration', 400);
    }
    $body = $modelResponse->get('body');
    $bodyContent = (string) $body;

    return new Response($bodyContent,
      200,
      [
        'Cache-Control' => 'no-cache, must-revalidate',
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigFields(): array {
    $default = [];
    $isInstalled = self::isInstalled('AWS Bedrock provider');
    if (!$isInstalled) {
      $default['#disabled'] = TRUE;
    }

    $fields = [];
    $fields["api_key"] = [
      '#type' => 'textfield',
      '#title' => 'API Key',
      '#required' => TRUE,
    ] + $default;
    $fields["api_secret"] = [
      '#type' => 'textfield',
      '#title' => 'API Secret',
      '#required' => TRUE,
    ] + $default;
    $fields["region"] = [
      '#type' => 'select',
      '#title' => 'AWS Region',
      '#options' => self::AWS_REGIONS,
      '#default_value' => $this->config->get("{$this->getPluginId()}_region") ?? current(self::AWS_REGIONS),
    ] + $default;
    if ($isInstalled) {
      $fields['region']['#states'] = [
        'disabled' => [
          ":input[name=\"{$this->getPluginId()}_add_custom_region_code\"]" => ['checked' => TRUE],
        ],
        'visible' => [
          ":input[name=\"{$this->getPluginId()}_add_custom_region_code\"]" => ['checked' => FALSE],
        ],
        'required' => [
          ":input[name=\"{$this->getPluginId()}_add_custom_region_code\"]" => ['checked' => FALSE],
        ],
      ];
    }
    $fields["add_custom_region_code"] = [
      '#type' => 'checkbox',
      '#title' => 'Use different region',
      '#description' => 'If your region is missing from the list, click the checkbox and add the region code below.',
    ] + $default;

    $fields["custom_region_code"] = [
      '#type' => 'textfield',
      '#title' => 'AWS Region Code',
      '#required' => FALSE,
      '#states' => [
        'disabled' => [
          ":input[name=\"{$this->getPluginId()}_add_custom_region_code\"]" => ['checked' => FALSE],
        ],
        'visible' => [
          ":input[name=\"{$this->getPluginId()}_add_custom_region_code\"]" => ['checked' => TRUE],
        ],
        'required' => [
          ":input[name=\"{$this->getPluginId()}_add_custom_region_code\"]" => ['checked' => TRUE],
        ],
      ],
    ] + $default;

    $fields["model"] = [
      '#type' => 'textfield',
      '#title' => 'Model',
      '#required' => TRUE,
      '#description' => $this->t("Provide one of Bedrock's available models with its version: </br>
                          - anthropic.claude </br>
                          - ai21.j2 </br>
                          - cohere.command </br>
                          - meta.llama2</br>
                          For example: <b>anthropic.claude-v2</b>"),
    ] + $default;

    $fields["model_config"] = [
      '#type' => 'textarea',
      '#title' => 'Model extra configuration',
      '#description' => $this->t('You can provide model configuration here. Each line should contain KEY:VALUE configuration setting. </br>
        Check AWS Documentation for more details: <a href="@aws_docs">AWS Documentation</a></br>
        For example:</br>
        <b>temperature: 0.1</b></br>
        <b>top_p: 0.9</b>', ['@aws_docs' => 'https://docs.aws.amazon.com/bedrock/latest/userguide/model-parameters.html']),
    ] + $default;

    return $fields;
  }

  /**
   * Returns Bedrock client.
   *
   * @return \Aws\BedrockRuntime\BedrockRuntimeClient
   *   Client object.
   */
  private function getClient(): BedrockRuntimeClient {
    return new BedrockRuntimeClient([
      'region' => $this->getRegion(),
      'version' => 'latest',
      'credentials' => new Credentials($this->getApiKey(), $this->getApiSecret()),
    ]);
  }

  /**
   * Returns auth key for the client.
   *
   * @return string
   *   The auth key.
   */
  private function getApiKey(): string {
    return $this->config->get("{$this->getPluginId()}_api_key") ?? '';
  }

  /**
   * Returns auth key for the client.
   *
   * @return string
   *   The auth key.
   */
  private function getApiSecret(): string {
    return $this->config->get("{$this->getPluginId()}_api_secret") ?? '';
  }

  /**
   * Returns auth key for the client.
   *
   * @return string
   *   The auth key.
   */
  private function getRegion(): string {
    $isCustom = $this->config->get("{$this->getPluginId()}_add_custom_region_code");
    if ($isCustom) {
      return $this->config->get("{$this->getPluginId()}_custom_region_code") ?? '';
    }
    return $this->config->get("{$this->getPluginId()}_region") ?? '';
  }

  /**
   * Returns auth key for the client.
   *
   * @return string
   *   The auth key.
   */
  private function getModel(): string {
    return $this->config->get("{$this->getPluginId()}_model") ?? '';
  }

  /**
   * Returns auth key for the client.
   *
   * @return array
   *   The auth key.
   */
  private function getModelConfig(): array {
    $modelConfig = $this->config->get("{$this->getPluginId()}_model_config") ?? '';
    if (!$modelConfig) {
      return [];
    }
    $modelConfigArr = [];
    $lines = explode("\r\n", $modelConfig);
    foreach ($lines as $line) {
      if (!empty($line)) {
        [$key, $value] = explode(": ", $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (is_numeric($value)) {
          if (str_contains($value, '.')) {
            $value = (float) $value;
          }
          else {
            $value = (int) $value;
          }
        }
        $modelConfigArr[$key] = $value;
      }
    }

    return $modelConfigArr;
  }

  /**
   * {@inheritdoc}
   */
  public function getTextAdapter(): AITextAdapter {
    return AITextAdapter::AWS;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * Invoke model based on provided model name.
   *
   * @param string $model
   *   Model name.
   * @param string $prompt
   *   The prompt.
   *
   * @return \Aws\Result|null
   *   Result from model.
   */
  public function invokeModel(string $model, string $prompt): ?Result {
    return match(1) {
      preg_match($this->claudeRegex, $model) => $this->invokeClaude($prompt),
      preg_match($this->ai21Regex, $model) => $this->invokeAi21($prompt),
      preg_match($this->cohereRegex, $model) => $this->invokeCohere($prompt),
      preg_match($this->metaRegex, $model) => $this->invokeMeta($prompt),
      default => NULL
    };
  }

  /**
   * {@inheritdoc}
   */
  public function validateFields(FormStateInterface &$form_state): void {
    $modelField = "{$this->getPluginId()}_model";
    $model = $form_state->getValue($modelField) ?? '';
    $isModelValid = match(1) {
      preg_match($this->claudeRegex, $model) => TRUE,
      preg_match($this->ai21Regex, $model) => TRUE,
      preg_match($this->cohereRegex, $model) => TRUE,
      preg_match($this->metaRegex, $model) => TRUE,
      default => FALSE
    };

    if (!$isModelValid) {
      $form_state->setErrorByName($modelField, $this->t('Invalid model. Available models are:</br>
        - anthropic.claude</br>
        - ai21.j2</br>
        - cohere.command</br>
        - meta.llama2'
      ));
    }
  }

  /**
   * Invoke Claude model.
   *
   * @param string $prompt
   *   The prompt.
   *
   * @return \Aws\Result|null
   *   Result from model.
   */
  private function invokeClaude(string $prompt): ?Result {
    $bedrockClient = $this->getClient();

    try {
      $modelConfig = $this->getModelConfig();
      $prompt = "\n\nHuman: {$prompt} \n\nAssistant:";
      $body = [
        'prompt' => $prompt,
        'max_tokens_to_sample' => 200,
        'temperature' => 0.5,
        'stop_sequences' => ["\n\nHuman:"],
      ];
      $body = array_merge($body, $modelConfig);

      return $bedrockClient->invokeModel([
        'contentType' => 'application/json',
        'body' => json_encode($body),
        'modelId' => $this->getModel(),
      ]);
    }
    catch (\Exception $e) {
      $this->logException($e->getMessage(), $e);
      return NULL;
    }
  }

  /**
   * Invoke the Ai21 model.
   *
   * @param string $prompt
   *   The prompt.
   *
   * @return \Aws\Result|null
   *   Result from model.
   */
  private function invokeAi21(string $prompt): ?Result {
    $bedrockClient = $this->getClient();

    try {
      $modelConfig = $this->getModelConfig();
      $body = [
        'prompt' => $prompt,
        'temperature' => 0.5,
        'maxTokens' => 200,
      ];
      $body = array_merge($body, $modelConfig);

      return $bedrockClient->invokeModel([
        'contentType' => 'application/json',
        'body' => json_encode($body),
        'modelId' => $this->getModel(),
      ]);
    }
    catch (\Exception $e) {
      $this->logException($e->getMessage(), $e);
      return NULL;
    }
  }

  /**
   * Invoke Cohere model.
   *
   * @param string $prompt
   *   The prompt.
   *
   * @return \Aws\Result|null
   *   Result from model.
   */
  private function invokeCohere(string $prompt): ?Result {
    $bedrockClient = $this->getClient();

    try {
      $modelConfig = $this->getModelConfig();
      $body = [
        'prompt' => $prompt,
        'max_tokens' => 200,
        'temperature' => 0.5,
        'p' => 0.5,
      ];
      $body = array_merge($body, $modelConfig);

      return $bedrockClient->invokeModel([
        'contentType' => 'application/json',
        'body' => json_encode($body),
        'modelId' => $this->getModel(),
      ]);
    }
    catch (\Exception $e) {
      $this->logException($e->getMessage(), $e);
      return NULL;
    }
  }

  /**
   * Invoke Meta model.
   *
   * @param string $prompt
   *   The prompt.
   *
   * @return \Aws\Result|null
   *   Result from model.
   */
  private function invokeMeta(string $prompt): ?Result {
    $bedrockClient = $this->getClient();

    try {
      $modelConfig = $this->getModelConfig();
      $body = [
        'prompt' => $prompt,
        'temperature' => 0.5,
        'max_gen_len' => 512,
      ];
      $body = array_merge($body, $modelConfig);

      return $bedrockClient->invokeModel([
        'contentType' => 'application/json',
        'body' => json_encode($body),
        'modelId' => $this->getModel(),
      ]);
    }
    catch (\Exception $e) {
      $this->logException($e->getMessage(), $e);
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function isInstalled($provider = ''): bool {
    if (ckeditor5_premium_features_check_dependency_class('\Aws\AwsClient')) {
      return TRUE;
    }

    $message = t('@provider is disabled because its required dependency <code>aws/aws-sdk-php</code> is not installed.', [
      '@provider' => $provider,
    ]);
    ckeditor5_premium_features_display_missing_dependency_warning($message);
    return FALSE;
  }
}
