<?php

namespace Drupal\ai_provider_openai\Plugin\AiProvider;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\File\FileExists;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\OpenAiBasedProviderClientBase;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\Moderation\ModerationInput;
use Drupal\ai\OperationType\Moderation\ModerationOutput;
use Drupal\ai\OperationType\Moderation\ModerationResponse;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextOutput;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\OperationType\TextToImage\TextToImageOutput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechOutput;
use Drupal\ai\Traits\OperationType\ChatTrait;
use Drupal\ai_provider_openai\OpenAiChatMessageIterator;
use Drupal\ai_provider_openai\OpenAiHelper;
use OpenAI\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'openai' provider.
 */
#[AiProvider(
  id: 'openai',
  label: new TranslatableMarkup('OpenAI'),
)]
class OpenAiProvider extends OpenAiBasedProviderClientBase {

  use ChatTrait;

  /**
   * The image mime types the image endpoints may return, with file extension.
   */
  protected const ALLOWED_IMAGE_MIME_TYPES = [
    'image/png' => 'png',
    'image/jpeg' => 'jpeg',
    'image/webp' => 'webp',
  ];

  /**
   * The helper to use.
   *
   * @var \Drupal\ai_provider_openai\OpenAiHelper
   */
  protected OpenAiHelper $openAiHelper;

  /**
   * Run moderation call, before a normal call.
   *
   * @var bool|null
   */
  protected bool|null $moderation = NULL;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $parent_instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $parent_instance->openAiHelper = $container->get('ai_provider_openai.helper');
    $parent_instance->logger = $container->get('logger.factory')->get('ai_provider_openai');
    return $parent_instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    // Load all models, and since OpenAI does not provide information about
    // which models does what, we need to hard code it in a helper function.
    $this->loadClient();
    return $this->getModels($operation_type ?? '', $capabilities);
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'chat',
      'embeddings',
      'moderation',
      'text_to_image',
      'text_to_speech',
      'speech_to_text',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    // If its GPT 3.5 the max tokens are 2048.
    if (preg_match('/gpt-3.5-turbo/', $model_id)) {
      $generalConfig['max_tokens']['default'] = 2048;
    }
    // Do this change for o1, o3, o4 and gpt-5 models.
    if (str_starts_with($model_id, 'gpt-5') || str_starts_with($model_id, 'o1') || str_starts_with($model_id, 'o3') || str_starts_with($model_id, 'o4')) {
      if (array_key_exists('max_tokens', $generalConfig)) {
        $generalConfig['max_completion_tokens'] = $generalConfig['max_tokens'];
        unset($generalConfig['max_tokens']);
      }
      // Also remove specific configs for these models.
      foreach (['frequency_penalty', 'top_p', 'presence_penalty', 'temperature'] as $config) {
        if (isset($generalConfig[$config])) {
          unset($generalConfig[$config]);
        }
      }
    }
    // Handle image generation models.
    if (strpos($model_id, 'gpt-image') === 0) {
      $generalConfig['quality'] = [
        'label' => 'Quality',
        'description' => 'The quality of the images that will be generated.',
        'type' => 'string',
        'required' => TRUE,
        'default' => 'standard',
      ];
    }

    // Handle GPT Image models.
    if (strpos($model_id, 'gpt-image') === 0) {
      $generalConfig['quality']['default'] = 'auto';
      $generalConfig['quality']['constraints'] = [
        'options' => [
          'auto',
          'low',
          'medium',
          'high',
        ],
      ];
      $generalConfig['size']['default'] = '1024x1024';
      $generalConfig['size']['constraints']['options'] = [
        '1024x1024',
        '1024x1536',
        '1536x1024',
        '1024x1792',
        '1792x1024',
      ];
      // GPT Image 1 uses output_format instead of response_format.
      $generalConfig['output_format'] = [
        'label' => 'Output Format',
        'description' => 'The format in which the generated images will be created.',
        'type' => 'string',
        'default' => 'png',
        'required' => FALSE,
        'constraints' => [
          'options' => [
            'png',
            'jpeg',
            'webp',
          ],
        ],
      ];
      // Remove response_format as it's not supported.
      unset($generalConfig['response_format']);
    }

    if ($model_id == 'text-embedding-3-large') {
      $generalConfig['dimensions']['default'] = 3072;
    }

    // @todo move this to an object once supported.
    if ($this->isReasoningModel($model_id)) {

      // See https://platform.openai.com/docs/api-reference/chat/create#chat_create-reasoning_effort.
      $generalConfig['reasoning_effort'] = [
        'type' => 'select',
        'label' => 'Reasoning Effort',
        'description' => 'Constrains effort on reasoning for reasoning models.',
        'default' => 'medium',
        'required' => TRUE,
        'constraints' => [
          'options' => [
            'none',
            'minimal',
            'low',
            'medium',
            'high',
            'xhigh',
          ],
        ],
      ];

      // If the model is 5.6 based, we need to set none as default.
      if (str_starts_with($model_id, 'gpt-5.6') || str_starts_with($model_id, 'o4')) {
        $generalConfig['reasoning_effort']['default'] = 'none';
      }

    }

    return $generalConfig;
  }

  /**
   * Enables moderation response, for all next coming responses.
   */
  public function enableModeration(): void {
    $this->moderation = TRUE;
  }

  /**
   * Disables moderation response, for all next coming responses.
   */
  public function disableModeration(): void {
    $this->moderation = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(string $api_key = ''): Client {
    // If the moderation is not set, we load it from the configuration.
    if (is_null($this->moderation)) {
      $this->moderation = $this->getConfig()->get('moderation');
    }
    return parent::getClient($api_key);
  }

  /**
   * {@inheritdoc}
   */
  protected function loadClient(): void {
    // Set custom endpoint from host config if available.
    if (!empty($this->getConfig()->get('host'))) {
      $this->setEndpoint($this->getConfig()->get('host'));
    }

    try {
      parent::loadClient();
    }
    catch (AiSetupFailureException $e) {
      throw new AiSetupFailureException('Failed to initialize OpenAI client: ' . $e->getMessage(), $e->getCode(), $e);
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
      $system_prompt = $input->getSystemPrompt();
      if ($system_prompt) {
        // If its o1 or o3 in it, we add it as a user message.
        if (preg_match('/(o1|o3)/i', $model_id)) {
          $chat_input[] = [
            'role' => 'user',
            'content' => $system_prompt,
          ];
        }
        else {
          $chat_input[] = [
            'role' => 'system',
            'content' => $system_prompt,
          ];
        }
      }
      /** @var \Drupal\ai\OperationType\Chat\ChatMessage $message */
      foreach ($input->getMessages() as $message) {
        $content = [
          [
            'type' => 'text',
            'text' => $message->getText(),
          ],
        ];
        if (method_exists($message, 'getFiles') && count($message->getFiles())) {
          foreach ($message->getFiles() as $file) {
            if ($file instanceof ImageFile) {
              $content[] = [
                'type' => 'image_url',
                'image_url' => [
                  'url' => $file->getAsBase64EncodedString(),
                ],
              ];
            }
            elseif ($file->getMimeType() === 'application/pdf') {
              $content[] = [
                'type' => 'file',
                'file' => [
                  'filename' => $file->getFilename(),
                  'file_data' => $file->getAsBase64EncodedString(),
                ],
              ];
            }
          }
        }
        elseif (count($message->getImages())) {
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

        // If it's a tool's response.
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
    // Moderation check - tokens are still there using json.
    $this->moderationEndpoints(json_encode($chat_input), $tags);

    $payload = [
      'model' => $model_id,
      'messages' => $chat_input,
    ] + $this->configuration;
    // If we want to add tools to the input.
    if (is_object($input) && method_exists($input, 'getChatTools') && $input->getChatTools()) {
      // Turn off reasoning mode if tools are used.
      if (!empty($payload['reasoning_effort'])) {
        $payload['reasoning_effort'] = 'none';
      }
      $payload['tools'] = $input->getChatTools()->renderToolsArray();
      foreach ($payload['tools'] as $key => $tool) {
        $payload['tools'][$key]['function']['strict'] = FALSE;
      }
    }
    // Check for structured json schemas.
    if (is_object($input) && method_exists($input, 'getChatStructuredJsonSchema') && $input->getChatStructuredJsonSchema()) {
      $payload['response_format'] = [
        'type' => 'json_schema',
        'json_schema' => $input->getChatStructuredJsonSchema(),
      ];
    }

    $usage = [];
    try {
      if ($this->streamed) {
        $payload['stream_options']['include_usage'] = TRUE;
        $response = $this->client->chat()->createStreamed($payload);
        $message = new OpenAiChatMessageIterator($response);
      }
      // If we are in a fibre, we will use a streamed response as the SDK
      // doesn't support direct async.
      elseif (\Fiber::getCurrent()) {
        $payload['stream_options']['include_usage'] = TRUE;
        $response = $this->client->chat()->createStreamed($payload);
        $stream = new OpenAiChatMessageIterator($response);
        // We consume the stream in a fiber.
        foreach ($stream as $chunk) {
          // Set the usage if it is available.
          if (is_array($chunk->getRaw()) && count($chunk->getRaw()) && !empty($chunk->getRaw()['usage'])) {
            $usage['usage'] = $chunk->getRaw()['usage'];
          }
          // Suspend fiber if we haven't finished yet.
          if (empty($stream->getFinishReason())) {
            \Fiber::suspend();
          }
        }

        // Create the final message from accumulated data.
        $message = $stream->reconstructChatOutput()->getNormalized();
      }
      else {
        $response = $this->client->chat()->create($payload)->toArray();
        // If tools are generated.
        $tools = [];
        if (!empty($response['choices'][0]['message']['tool_calls'])) {
          foreach ($response['choices'][0]['message']['tool_calls'] as $tool) {
            $arguments = Json::decode($tool['function']['arguments']);
            $tools[] = new ToolsFunctionOutput($input->getChatTools()->getFunctionByName($tool['function']['name']), $tool['id'], $arguments);
          }
        }
        $message = new ChatMessage($response['choices'][0]['message']['role'], $response['choices'][0]['message']['content'] ?? "", []);
        if (!empty($tools)) {
          $message->setTools($tools);
        }
        $usage['usage'] = $response['usage'] ?? [];
      }
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }

    $chat_output = new ChatOutput($message, $response, []);

    // We only set the token usage if its not streamed or in a fiber.
    if (count($usage)) {
      $this->setChatTokenUsage($chat_output, $usage);
    }

    return $chat_output;
  }

  /**
   * {@inheritdoc}
   */
  public function moderation(string|ModerationInput $input, ?string $model_id = NULL, array $tags = []): ModerationOutput {
    $this->loadClient();
    // Normalize the prompt if needed.
    if ($input instanceof ModerationInput) {
      $input = $input->getPrompt();
    }
    $payload = [
      'model' => $model_id ?? 'omni-moderation-latest',
      'input' => $input,
    ] + $this->configuration;
    $response = $this->client->moderations()->create($payload)->toArray();
    $normalized = new ModerationResponse($response['results'][0]['flagged'], $response['results'][0]['category_scores']);
    return new ModerationOutput($normalized, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToImage(string|TextToImageInput $input, string $model_id, array $tags = []): TextToImageOutput {
    $this->loadClient();
    // Normalize the input if needed.
    if ($input instanceof TextToImageInput) {
      $input = $input->getText();
    }
    // Moderation.
    $this->moderationEndpoints($input, $tags);
    // Handle parameter naming differences between models.
    $payload = [
      'model' => $model_id,
      'prompt' => $input,
    ] + $this->configuration;
    // Always request base64 encoded images so the image data comes directly
    // from the API response and never has to be downloaded from a URL. GPT
    // Image models do not support the response_format parameter and always
    // return base64 encoded data.
    if (strpos($model_id, 'gpt-image') === 0) {
      unset($payload['response_format']);
    }
    else {
      $payload['response_format'] = 'b64_json';
    }

    try {
      $response = $this->client->images()->create($payload)->toArray();
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }
    $images = [];

    if (empty($response['data'][0])) {
      throw new AiResponseErrorException('No image data found in the response.');
    }
    // Process the image response.
    foreach ($response['data'] as $data) {
      // Check if this is a gpt-image-1 response.
      $is_gpt_image = strpos($model_id, 'gpt-image') === 0 || isset($data['revised_prompt']);

      if (empty($data['b64_json'])) {
        $this->logger->error('No base64 image data found in response.');
        continue;
      }
      $image_content = base64_decode($data['b64_json'], TRUE);
      if ($image_content === FALSE) {
        $this->logger->error('The image data in the response is not valid base64.');
        continue;
      }
      // Determine the mime type from the actual binary data, so that only
      // real images can end up in the output.
      $mime_type = $this->detectMimeType($image_content, static::ALLOWED_IMAGE_MIME_TYPES);
      if ($mime_type === NULL) {
        $this->logger->error('The returned image data is not a valid image.');
        continue;
      }
      $file_ext = static::ALLOWED_IMAGE_MIME_TYPES[$mime_type];
      $images[] = new ImageFile($image_content, $mime_type, 'openai.' . $file_ext);
    }

    // If no images were successfully created, throw an error.
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
    // Normalize the input if needed.
    if ($input instanceof TextToSpeechInput) {
      $input = $input->getText();
    }
    // Moderation.
    $this->moderationEndpoints($input, $tags);
    // Send the request.
    $payload = [
      'model' => $model_id,
      'input' => $input,
    ] + $this->configuration;
    try {
      $response = $this->client->audio()->speech($payload);
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }
    // Check that the returned binary actually is audio data. Raw PCM has no
    // header to detect a mime type from, so it cannot be checked.
    if (($payload['response_format'] ?? 'mp3') !== 'pcm' && $this->detectMimeType((string) $response, 'audio') === NULL) {
      throw new AiResponseErrorException('The returned data is not valid audio data.');
    }
    $output = new AudioFile($response, 'audio/mpeg', 'openai.mp3');

    // Return a normalized response.
    return new TextToSpeechOutput([$output], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function speechToText(string|SpeechToTextInput $input, string $model_id, array $tags = []): SpeechToTextOutput {
    $this->loadClient();
    // Normalize the input if needed.
    if ($input instanceof SpeechToTextInput) {
      $input = $input->getBinary();
    }
    // The raw file has to become a resource, so we save a temporary file first.
    $path = $this->fileSystem->saveData($input, 'temporary://speech_to_text.mp3', FileExists::Replace);
    $input = fopen($path, 'r');
    $payload = [
      'model' => $model_id,
      'file' => $input,
    ] + $this->configuration;
    try {
      $response = $this->client->audio()->transcribe($payload)->toArray();
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }

    return new SpeechToTextOutput($response['text'], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function embeddings(string|EmbeddingsInput $input, string $model_id, array $tags = []): EmbeddingsOutput {
    $this->loadClient();
    // Normalize the input if needed.
    if ($input instanceof EmbeddingsInput) {
      $input = $input->getPrompt();
    }
    // Moderation.
    $this->moderationEndpoints($input, $tags);
    // Send the request.
    $payload = [
      'model' => $model_id,
      'input' => $input,
    ] + $this->configuration;
    try {
      $response = $this->client->embeddings()->create($payload)->toArray();
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }

    return new EmbeddingsOutput($response['data'][0]['embedding'], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupData(): array {
    return [
      'key_config_name' => 'api_key',
      'default_models' => [
        'chat' => 'gpt-5.4',
        'chat_with_image_vision' => 'gpt-5.4',
        'chat_with_complex_json' => 'gpt-5.4',
        'chat_with_tools' => 'gpt-5.4',
        'chat_with_structured_response' => 'gpt-5.4',
        'text_to_image' => 'gpt-image-1',
        'embeddings' => 'text-embedding-3-small',
        'moderation' => 'omni-moderation-latest',
        'text_to_speech' => 'tts-1-hd',
        'speech_to_text' => 'whisper-1',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function postSetup(): void {
    // Throw an error on installation with rate limit.
    $this->openAiHelper->testRateLimit($this->loadApiKey());
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsVectorSize(string $model_id): int {
    return match($model_id) {
      'text-embedding-ada-002', 'text-embedding-3-small' => 1536,
      'text-embedding-3-large' => 3072,
      default => 0,
    };
  }

  /**
   * Moderation endpoints to run before the normal call.
   *
   * @param string $prompt
   *   The prompt to moderate.
   * @param array $tags
   *   Operation tags. If 'skip_moderation' is present, the check is bypassed
   *   for this call only without altering the persistent moderation state.
   *
   * @throws \Drupal\ai\Exception\AiUnsafePromptException
   */
  public function moderationEndpoints(string $prompt, array $tags = []): void {
    $this->getClient();
    // If moderation is disabled globally or the caller has tagged this call to
    // skip moderation, bypass the check.
    if (!$this->moderation || in_array('skip_moderation', $tags)) {
      return;
    }
    $payload = [
      'model' => 'omni-moderation-latest',
      'input' => $prompt,
    ] + $this->configuration;
    try {
      $response = $this->client->moderations()->create($payload)->toArray();
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      if (strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }

    if (!empty($response['results'][0]['flagged'])) {
      throw new AiUnsafePromptException('The prompt was flagged by the moderation model.');
    }
  }

  /**
   * Obtains a list of models from OpenAI and caches the result.
   *
   * This method does its best job to filter out deprecated or unused models.
   * The OpenAI API endpoint does not have a way to filter those out yet.
   *
   * @param string $operation_type
   *   The bundle to filter models by.
   * @param array $capabilities
   *   The capabilities to filter models by.
   *
   * @return array
   *   A filtered list of public models.
   */
  public function getModels(string $operation_type, $capabilities): array {
    $models = [];

    $cache_key = 'openai_models_' . $operation_type . '_' . Crypt::hashBase64(Json::encode($capabilities));
    $cache_data = $this->cacheBackend->get($cache_key);

    if (!empty($cache_data)) {
      return $cache_data->data;
    }

    $list = $this->client->models()->list()->toArray();

    foreach ($list['data'] as $model) {
      if ($model['owned_by'] === 'openai-dev') {
        continue;
      }

      // Basic model type filtering based on operation type.
      switch ($operation_type) {
        case 'chat':
          // Include all GPT models for chat operations.
          if (!preg_match('/^(gpt|o1|o3|o4)/i', $model['id'])) {
            continue 2;
          }
          break;

        case 'embeddings':
          if (!preg_match('/^(text-embedding)/i', trim($model['id']))) {
            continue 2;
          }
          break;

        case 'moderation':
          if (!preg_match('/^(text-moderation|omni-moderation)/i', $model['id'])) {
            continue 2;
          }
          break;

        case 'text_to_image':
          if (!preg_match('/^(clip|gpt-image)/i', $model['id'])) {
            continue 2;
          }
          break;

        case 'speech_to_text':
          if (!preg_match('/^(whisper)/i', $model['id'])) {
            continue 2;
          }
          break;

        case 'text_to_speech':
          if (!preg_match('/^(tts)/i', $model['id'])) {
            continue 2;
          }
          break;
      }

      // If its a vision model, we only allow it if the capability is set.
      if (in_array(AiModelCapability::ChatWithImageVision, $capabilities) && !preg_match('/^(gpt-4\.1(?![0-9])|gpt-5|gpt-4o|gpt-4-turbo|vision|o4)/i', $model['id'])) {
        continue;
      }

      // Include all GPT models for JSON output capability.
      if (in_array(AiModelCapability::ChatJsonOutput, $capabilities) && !preg_match('/^(gpt-4|gpt-4o|o1|o3|gpt-4-turbo|gpt-5)/i', $model['id'])) {
        continue;
      }
      // Only allow models that support tools/function calling.
      if (in_array(AiModelCapability::ChatTools, $capabilities) && !preg_match('/^(gpt-4\.1(?![0-9])|gpt-4o|gpt-4-turbo|gpt-5|o1|o3|o4)/i', $model['id'])) {
        continue;
      }
      // Only allow models that support structured responses.
      if (in_array(AiModelCapability::ChatStructuredResponse, $capabilities) && !preg_match('/^(gpt-4\.1(?![0-9])|gpt-4o|gpt-4-turbo|gpt-5|o1|o3|o4)/i', $model['id'])) {
        continue;
      }
      // Only allow models that support both tools and structured responses.
      if (in_array(AiModelCapability::ChatCombinedToolsAndStructuredResponse, $capabilities) && !preg_match('/^(gpt-4\.1(?![0-9])|gpt-4o|gpt-4-turbo|gpt-5|o1|o3|o4)/i', $model['id'])) {
        continue;
      }
      // Don't allow audio or video for now.
      if (in_array(AiModelCapability::ChatWithAudio, $capabilities)) {
        continue;
      }
      if (in_array(AiModelCapability::ChatWithVideo, $capabilities)) {
        continue;
      }

      $models[$model['id']] = $model['id'];
    }

    if ($operation_type == 'moderation') {
      $models['text-moderation-latest'] = 'text-moderation-latest';
      $models['omni-moderation-latest'] = 'omni-moderation-latest';
    }

    if (!empty($models)) {
      asort($models);
      $this->cacheBackend->set($cache_key, $models);
    }

    return $models;
  }

  /**
   * Detects the mime type of binary data and validates it.
   *
   * @param string $binary
   *   The binary data to check.
   * @param array|string $allowed
   *   Either an array of accepted mime types keyed by mime type, or a
   *   primary mime type such as "image" or "audio" that the detected mime
   *   type has to belong to.
   *
   * @return string|null
   *   The detected mime type, or NULL if it is not allowed.
   */
  protected function detectMimeType(string $binary, array|string $allowed): ?string {
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($binary);
    if (!is_string($mime_type)) {
      return NULL;
    }
    if (is_array($allowed)) {
      return isset($allowed[$mime_type]) ? $mime_type : NULL;
    }
    return str_starts_with($mime_type, $allowed . '/') ? $mime_type : NULL;
  }

  /**
   * Heuristic to determine if a model is a reasoning model.
   *
   * Reasoning models have different token usage breakdowns and settings.
   *
   * @param string $modelId
   *   The model ID to check.
   *
   * @return bool
   *   TRUE if the model is likely a reasoning model, FALSE otherwise.
   */
  protected function isReasoningModel(string $modelId): bool {
    $id = strtolower($modelId);
    // All gpt-5 variants (base, dated, mini, nano, chat-latest, etc.)
    if (str_starts_with($id, 'gpt-5')) {
      return TRUE;
    }
    // All o-series models (o1, o2, o3, etc. including -mini variants)
    if (preg_match('/^o\d/', $id)) {
      return TRUE;
    }
    return FALSE;
  }

}
