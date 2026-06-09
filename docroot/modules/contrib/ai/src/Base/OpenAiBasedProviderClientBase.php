<?php

namespace Drupal\ai\Base;

use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileExists;
use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai\Dto\ChatProviderLimitsDto;
use Drupal\ai\Enum\AiProviderCapability;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\OpenAiTypeStreamedChatMessageIterator;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\Moderation\ModerationInput;
use Drupal\ai\OperationType\Moderation\ModerationInterface;
use Drupal\ai\OperationType\Moderation\ModerationOutput;
use Drupal\ai\OperationType\Moderation\ModerationResponse;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInterface;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextOutput;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\OperationType\TextToImage\TextToImageInterface;
use Drupal\ai\OperationType\TextToImage\TextToImageOutput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInterface;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechOutput;
use Drupal\ai\ProviderClient\OpenAiBasedProviderClientInterface;
use Drupal\ai\Traits\OperationType\EmbeddingsTrait;
use OpenAI\Client;
use OpenAI\Responses\Meta\MetaInformation;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Base class for OpenAI-based provider implementations.
 */
abstract class OpenAiBasedProviderClientBase extends AiProviderClientBase implements
  OpenAiBasedProviderClientInterface,
  ChatInterface,
  ModerationInterface,
  EmbeddingsInterface,
  TextToSpeechInterface,
  SpeechToTextInterface,
  TextToImageInterface {

  use EmbeddingsTrait;

  /**
   * The OpenAI Client.
   *
   * @var \OpenAI\Client|null
   */
  protected $client;

  /**
   * API Key.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * Custom endpoint URL.
   *
   * @var string
   */
  protected string $endpoint = '';

  /**
   * The rate limit headers.
   *
   * @var array
   */
  protected array $rateLimitHeaders = [];

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    if (!$this->hasAuthentication() && !$this->getConfig()->get('api_key')) {
      return FALSE;
    }
    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    $this->apiKey = $authentication;
    $this->client = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(string $api_key = ''): Client {
    if ($api_key) {
      $this->setAuthentication($api_key);
    }
    $this->loadClient();
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAuthentication(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedCapabilities(): array {
    return [
      AiProviderCapability::StreamChatOutput,
      AiProviderCapability::ChatFiberSupport,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint(): ?string {
    return !empty($this->endpoint) ? $this->endpoint : NULL;
  }

  /**
   * Loads the OpenAI Client with authentication if not initialized.
   *
   * @throws \Drupal\ai\Exception\AiSetupFailureException
   *   Thrown when the API key cannot be loaded or authentication setup fails.
   */
  protected function loadClient(): void {
    if (empty($this->client)) {
      $this->client = $this->createClient();
    }
  }

  /**
   * Sets a custom endpoint for the client.
   *
   * @param string $endpoint
   *   The endpoint URL.
   */
  protected function setEndpoint(string $endpoint): void {
    $this->endpoint = $endpoint;
  }

  /**
   * Gets the current HTTP client.
   *
   * @return \Psr\Http\Client\ClientInterface
   *   The HTTP client instance.
   */
  protected function getHttpClient(): ClientInterface {
    return $this->httpClient;
  }

  /**
   * Sets a custom HTTP client.
   *
   * @param \Psr\Http\Client\ClientInterface $httpClient
   *   The HTTP client instance.
   */
  protected function setHttpClient(ClientInterface $httpClient): void {
    $this->httpClient = $httpClient;
  }

  /**
   * Creates the OpenAI client with proper configuration.
   *
   * @return \OpenAI\Client
   *   The configured OpenAI client instance.
   *
   * @throws \Drupal\ai\Exception\AiSetupFailureException
   *   Thrown when the API key cannot be loaded or authentication setup fails.
   */
  protected function createClient(): Client {
    $clientFactory = \OpenAI::factory();

    // Only set the API key if it is not empty.
    if ($this->hasAuthentication()) {
      // Only set authentication when not set.
      if (empty($this->apiKey)) {
        try {
          $key = $this->loadApiKey();
        }
        catch (AiSetupFailureException $e) {
          $this->loggerFactory->get('ai')->error($e->getMessage());
        }
        if (!empty($key)) {
          $this->setAuthentication($key);
        }
      }
      if (!empty($this->apiKey)) {
        $clientFactory = $clientFactory->withApiKey($this->apiKey);
      }
    }

    $client = $clientFactory->withHttpClient($this->httpClient);

    // If the configuration has a custom endpoint, we set it.
    if ($this->getEndpoint()) {
      $client = $client->withBaseUri($this->getEndpoint());
    }

    return $client->make();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    $module_name = $this->pluginDefinition['provider'];
    return $this->configFactory->get($module_name . '.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    $module_name = $this->pluginDefinition['provider'];
    $module_path = $this->moduleHandler->getModule($module_name)->getPath();
    $definition_file = $module_path . '/definitions/api_defaults.yml';

    try {
      return Yaml::parseFile($definition_file);
    }
    catch (\Exception $e) {
      throw new \RuntimeException('Failed to parse API definition file ' . $definition_file . ': ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();
    // Normalize the input if needed.
    $chat_input = $input;
    if ($input instanceof ChatInput) {
      $chat_input = [];
      // Add a system role if wanted.
      if ($this->chatSystemRole) {
        $chat_input[] = [
          'role' => 'system',
          'content' => $this->chatSystemRole,
        ];
      }
      /** @var \Drupal\ai\OperationType\Chat\ChatMessage $message */
      foreach ($input->getMessages() as $message) {
        // For basic text-only messages, use simple string content.
        $content = $message->getText();

        // For messages with images, use the complex content format.
        if (count($message->getImages())) {
          $content = [
            [
              'type' => 'text',
              'text' => $message->getText(),
            ],
          ];

          foreach ($message->getImages() as $image) {
            $content[] = [
              'type' => 'image_url',
              'image_url' => [
                'url' => $image->getAsBase64EncodedString(),
              ],
            ];
          }
        }

        $new_message = [
          'role' => $message->getRole(),
          'content' => $content,
        ];

        // If it is a tools' response.
        if ($message->getToolsId()) {
          $new_message['tool_call_id'] = $message->getToolsId();
        }

        // If we want the results from some older tools call.
        if ($message->getTools()) {
          $new_message['tool_calls'] = $message->getRenderedTools();
        }

        $chat_input[] = $new_message;
      }
    }

    $payload = [
      'model' => $model_id,
      'messages' => $chat_input,
    ] + $this->configuration;

    if (method_exists($input, 'getChatTools') && $input->getChatTools()) {
      $payload['tools'] = $input->getChatTools()->renderToolsArray();
      foreach ($payload['tools'] as $key => $tool) {
        $payload['tools'][$key]['function']['strict'] = FALSE;
      }
    }

    if (method_exists($input, 'getChatStructuredJsonSchema') && $input->getChatStructuredJsonSchema()) {
      $payload['response_format'] = [
        'type' => 'json_schema',
        'json_schema' => $input->getChatStructuredJsonSchema(),
      ];
    }

    try {
      if ($this->streamed && in_array(AiProviderCapability::StreamChatOutput, $this->getSupportedCapabilities())) {
        $payload['stream_options'] = [
          'include_usage' => TRUE,
        ];
        $response = $this->client->chat()->createStreamed($payload);
        $message = new OpenAiTypeStreamedChatMessageIterator($response);
        $chat_output = new ChatOutput($message, $response, []);
      }
      // If we are in a fibre, we will use a streamed response as the SDK
      // doesn't support direct async.
      elseif (\Fiber::getCurrent() && in_array(AiProviderCapability::StreamChatOutput, $this->getSupportedCapabilities())) {
        $payload['stream_options'] = [
          'include_usage' => TRUE,
        ];
        $response = $this->client->chat()->createStreamed($payload);
        $stream = new OpenAiTypeStreamedChatMessageIterator($response);
        // We consume the stream in a fiber.
        foreach ($stream as $chunk) {
          // Suspend fiber if we haven't finished yet.
          if (empty($stream->getFinishReason()) && !empty($chunk)) {
            \Fiber::suspend();
          }
        }

        // Create the final message from accumulated data.
        $message = $stream->reconstructChatOutput()->getNormalized();
        $chat_output = new ChatOutput($message, $response, []);
      }
      else {
        $initialResponse = $this->client->chat()->create($payload);
        $this->captureRateLimitHeaders($initialResponse?->meta());
        $response = $initialResponse->toArray();
        $message = new ChatMessage($response['choices'][0]['message']['role'], $response['choices'][0]['message']['content'] ?? '', []);

        // Handle tool calls if present.
        if (isset($response['choices'][0]['finish_reason']) && $response['choices'][0]['finish_reason'] == 'tool_calls') {
          $tools = [];
          if (!empty($response['choices'][0]['message']['tool_calls'])) {
            foreach ($response['choices'][0]['message']['tool_calls'] as $tool) {
              $arguments = Json::decode($tool['function']['arguments']);
              $tools[] = new ToolsFunctionOutput($input->getChatTools()->getFunctionByName($tool['function']['name']), $tool['id'], $arguments);
            }
          }
          if (!empty($tools)) {
            $message->setTools($tools);
          }
        }
        $chat_output = new ChatOutput($message, $response, []);
        if ($rate_limits = $this->mapRateLimits()) {
          $chat_output->setRateLimits($rate_limits);
        }
        $chat_output = $this->setChatTokenUsage($chat_output, $response);
      }

      return $chat_output;
    }
    catch (\Throwable $e) {
      $this->handleApiThrowable($e);
      throw $e;
    }
    return new ChatOutput($message, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function moderation(string|ModerationInput $input, ?string $model_id = NULL, array $tags = []): ModerationOutput {
    $this->loadClient();

    // Do not allow empty model IDs for moderation.
    if (empty($model_id)) {
      throw new AiRequestErrorException('Model ID is required for moderation requests.');
    }

    if ($input instanceof ModerationInput) {
      $input = $input->getPrompt();
    }

    $payload = [
      'model' => $model_id,
      'input' => $input,
    ] + $this->configuration;

    try {
      $response = $this->client->moderations()->create($payload)->toArray();
    }
    catch (\Throwable $e) {
      $this->handleApiThrowable($e);
      throw $e;
    }
    $normalized = new ModerationResponse($response['results'][0]['flagged'], $response['results'][0]['category_scores']);
    return new ModerationOutput($normalized, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToImage(string|TextToImageInput $input, string $model_id, array $tags = []): TextToImageOutput {
    $this->loadClient();

    if ($input instanceof TextToImageInput) {
      $input = $input->getText();
    }

    $payload = [
      'model' => $model_id,
      'prompt' => $input,
    ] + $this->configuration;

    try {
      $response = $this->client->images()->create($payload)->toArray();
    }
    catch (\Throwable $e) {
      $this->handleApiThrowable($e);
    }

    $images = [];
    if (empty($response['data'][0])) {
      throw new AiResponseErrorException('No image data found in the response.');
    }

    foreach ($response['data'] as $data) {
      if (isset($data['b64_json'])) {
        $images[] = new ImageFile(base64_decode($data['b64_json']), 'image/png', 'generated.png');
      }
      elseif (isset($data['url']) && !empty($data['url'])) {
        try {
          $image_content = file_get_contents($data['url']);
          if ($image_content !== FALSE) {
            $images[] = new ImageFile($image_content, 'image/png', 'generated.png');
          }
        }
        catch (\Throwable $e) {
          $this->loggerFactory->get('ai')->error('Failed to fetch image from URL @url: @message', [
            '@url' => $data['url'],
            '@message' => $e->getMessage(),
          ]);
        }
      }
    }

    if (empty($images)) {
      throw new AiResponseErrorException('Failed to process any valid images from the API response.');
    }

    return new TextToImageOutput($images, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToSpeech(string|TextToSpeechInput $input, string $model_id, array $tags = []): TextToSpeechOutput {
    $this->loadClient();

    if ($input instanceof TextToSpeechInput) {
      $input = $input->getText();
    }

    $payload = [
      'model' => $model_id,
      'input' => $input,
    ] + $this->configuration;

    try {
      $response = $this->client->audio()->speech($payload);
    }
    catch (\Throwable $e) {
      $this->handleApiThrowable($e);
      throw $e;
    }
    $output = new AudioFile($response, 'audio/mpeg', 'speech.mp3');
    return new TextToSpeechOutput([$output], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function speechToText(string|SpeechToTextInput $input, string $model_id, array $tags = []): SpeechToTextOutput {
    $this->loadClient();

    if ($input instanceof SpeechToTextInput) {
      $input = $input->getBinary();
    }

    $path = $this->fileSystem->saveData($input, 'temporary://speech_to_text.mp3', FileExists::Replace);
    $input = fopen($path, 'r');

    $payload = [
      'model' => $model_id,
      'file' => $input,
    ] + $this->configuration;

    try {
      $response = $this->client->audio()->transcribe($payload)->toArray();
    }
    catch (\Throwable $e) {
      $this->handleApiThrowable($e);
      throw $e;
    }
    finally {
      if (is_resource($input)) {
        fclose($input);
      }
    }
    return new SpeechToTextOutput($response['text'], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function embeddings(string|EmbeddingsInput $input, string $model_id, array $tags = []): EmbeddingsOutput {
    $this->loadClient();

    if ($input instanceof EmbeddingsInput) {
      $input = $input->getPrompt();
    }

    $payload = [
      'model' => $model_id,
      'input' => $input,
    ] + $this->configuration;

    try {
      $response = $this->client->embeddings()->create($payload)->toArray();
    }
    catch (\Throwable $e) {
      $this->handleApiThrowable($e);
      throw $e;
    }
    return new EmbeddingsOutput($response['data'][0]['embedding'], $response, []);
  }

  /**
   * Handle API exceptions consistently.
   *
   * @param \Exception $e
   *   The exception to handle.
   *
   * @throws \Drupal\ai\Exception\AiRateLimitException
   * @throws \Drupal\ai\Exception\AiQuotaException
   * @throws \Exception
   */
  protected function handleApiException(\Exception $e): void {
    if (strpos($e->getMessage(), 'Request too large') !== FALSE || strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
      throw new AiRateLimitException($e->getMessage());
    }
    if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
      throw new AiQuotaException($e->getMessage());
    }
    throw $e;
  }

  /**
   * Handle any Throwable from the API client.
   *
   * Call sites catching \Throwable should route through this method. PHP
   * errors (e.g. \TypeError from the OpenAI PHP client) are wrapped in an
   * AiResponseErrorException so that all consumers receive a regular
   * exception they can catch. Everything else delegates to
   * handleApiException(), preserving backwards compatibility with
   * subclasses that override handleApiException(\Exception $e).
   *
   * Subclasses that want a single hook covering both exceptions and errors
   * may override this method directly.
   *
   * Two methods exist — handleApiException(\Exception) and
   * handleApiThrowable(\Throwable) — because a previous widening of
   * handleApiException() to \Throwable was an incompatible change
   * that broke subclasses (e.g. the Anthropic provider) overriding it with
   * \Exception. The wider hook was reintroduced here as an opt-in method
   * so contributed providers can keep their existing overrides.
   *
   * @param \Throwable $e
   *   The throwable to handle.
   *
   * @throws \Drupal\ai\Exception\AiRateLimitException
   * @throws \Drupal\ai\Exception\AiQuotaException
   * @throws \Drupal\ai\Exception\AiResponseErrorException
   * @throws \Exception
   */
  protected function handleApiThrowable(\Throwable $e): void {
    // Wrap PHP errors (TypeError, ValueError, etc.) in an
    // AiResponseErrorException so consumers can catch a single exception type.
    if ($e instanceof \Error) {
      throw new AiResponseErrorException($e->getMessage(), $e->getCode(), $e);
    }
    $this->handleApiException($e);
  }

  /**
   * Helper function to set the token usage on chat output.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatOutput $chat_output
   *   The chat output to set the token usage on.
   * @param array $response
   *   The response array containing token usage.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The chat output with token usage set.
   */
  protected function setChatTokenUsage(ChatOutput $chat_output, array $response): ChatOutput {
    $chat_output->setTokenUsage(new TokenUsageDto(
      input: $response['usage']['prompt_tokens'] ?? NULL,
      output: $response['usage']['completion_tokens'] ?? NULL,
      total: $response['usage']['total_tokens'] ?? NULL,
      reasoning: $response['usage']['completion_tokens_details']['reasoning_tokens'] ?? NULL,
      cached: $response['usage']['prompt_tokens_details']['cached_tokens'] ?? NULL,
    ));
    return $chat_output;
  }

  /**
   * Maps rate limit headers to a ChatProviderLimitsDto object.
   *
   * @return \Drupal\ai\Dto\ChatProviderLimitsDto|null
   *   The rate limits DTO or NULL if no headers are available.
   */
  protected function mapRateLimits(): ?ChatProviderLimitsDto {
    if (empty($this->rateLimitHeaders)) {
      return NULL;
    }

    $dto = new ChatProviderLimitsDto(
      rateLimitMaxRequests: isset($this->rateLimitHeaders['x-ratelimit-limit-requests']) ? (int) $this->rateLimitHeaders['x-ratelimit-limit-requests'] : NULL,
      rateLimitMaxTokens: isset($this->rateLimitHeaders['x-ratelimit-limit-tokens']) ? (int) $this->rateLimitHeaders['x-ratelimit-limit-tokens'] : NULL,
      rateLimitRemainingRequests: isset($this->rateLimitHeaders['x-ratelimit-remaining-requests']) ? (int) $this->rateLimitHeaders['x-ratelimit-remaining-requests'] : NULL,
      rateLimitRemainingTokens: isset($this->rateLimitHeaders['x-ratelimit-remaining-tokens']) ? (int) $this->rateLimitHeaders['x-ratelimit-remaining-tokens'] : NULL,
      rateLimitResetRequests: isset($this->rateLimitHeaders['x-ratelimit-reset-requests']) ? $this->parseResetTime($this->rateLimitHeaders['x-ratelimit-reset-requests']) : NULL,
      rateLimitResetTokens: isset($this->rateLimitHeaders['x-ratelimit-reset-tokens']) ? $this->parseResetTime($this->rateLimitHeaders['x-ratelimit-reset-tokens']) : NULL,
    );

    if ($dto->empty()) {
      return NULL;
    }
    return $dto;

  }

  /**
   * Parses reset time to seconds.
   *
   * @param string $resetTime
   *   The reset time string.
   *
   * @return int|null
   *   The time in seconds or NULL.
   */
  protected function parseResetTime(string $resetTime): ?int {
    if (preg_match('/(\d+)m(\d+)s/', $resetTime, $matches)) {
      return ((int) $matches[1] * 60) + (int) $matches[2];
    }
    if (preg_match('/(\d+)s/', $resetTime, $matches)) {
      return (int) $matches[1];
    }
    if (preg_match('/(\d+)ms/', $resetTime, $matches)) {
      return 0;
    }
    if (is_numeric($resetTime)) {
      return max(0, (int) $resetTime - time());
    }
    return NULL;
  }

  /**
   * Captures rate limit headers from the response object.
   *
   * @param \OpenAI\Responses\Meta\MetaInformation|null $metaInformation
   *   Open AI meta information object.
   */
  protected function captureRateLimitHeaders(?MetaInformation $metaInformation): void {
    $this->rateLimitHeaders['x-ratelimit-limit-requests'] = $metaInformation?->requestLimit?->limit;
    $this->rateLimitHeaders['x-ratelimit-remaining-requests'] = $metaInformation?->requestLimit?->remaining;
    $this->rateLimitHeaders['x-ratelimit-reset-requests'] = $metaInformation?->requestLimit?->reset;
    $this->rateLimitHeaders['x-ratelimit-limit-tokens'] = $metaInformation?->tokenLimit?->limit;
    $this->rateLimitHeaders['x-ratelimit-remaining-tokens'] = $metaInformation?->tokenLimit?->remaining;
    $this->rateLimitHeaders['x-ratelimit-reset-tokens'] = $metaInformation?->tokenLimit?->reset;
  }

}
