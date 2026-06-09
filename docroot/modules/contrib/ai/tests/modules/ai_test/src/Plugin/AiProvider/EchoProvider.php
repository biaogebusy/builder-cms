<?php

namespace Drupal\ai_test\Plugin\AiProvider;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\ai\Mock\MockIterator;
use Drupal\Tests\ai\Mock\MockStreamedChatIterator;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResult;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationInput;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationInterface;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationItem;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationOutput;
use Drupal\ai\OperationType\TextClassification\TextClassificationInput;
use Drupal\ai\OperationType\TextClassification\TextClassificationInterface;
use Drupal\ai\OperationType\TextClassification\TextClassificationItem;
use Drupal\ai\OperationType\TextClassification\TextClassificationOutput;
use Drupal\ai\OperationType\ImageToImage\ImageToImageInput;
use Drupal\ai\OperationType\ImageToImage\ImageToImageInterface;
use Drupal\ai\OperationType\ImageToImage\ImageToImageOutput;
use Drupal\ai\OperationType\InputInterface;
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
use Drupal\ai\Traits\OperationType\ImageToImageTrait;
use Drupal\ai_test\OperationType\Echo\EchoInput;
use Drupal\ai_test\OperationType\Echo\EchoInterface;
use Drupal\ai_test\OperationType\Echo\EchoOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the 'mock' provider.
 */
#[AiProvider(
  id: 'echoai',
  label: new TranslatableMarkup('EchoAI'),
)]
class EchoProvider extends AiProviderClientBase implements
  ChatInterface,
  EmbeddingsInterface,
  ModerationInterface,
  SpeechToTextInterface,
  TextToSpeechInterface,
  ImageClassificationInterface,
  TextToImageInterface,
  EchoInterface,
  ImageToImageInterface,
  TextClassificationInterface {

  use ImageToImageTrait;

  /**
   * The module handler interface.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The variable for the sub directory to search in.
   *
   * @var string
   */
  public static string $requestTestSubDirectory = 'tests/resources/ai_test/requests';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $parent_instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $parent_instance->moduleHandler = $container->get('module_handler');
    $parent_instance->entityTypeManager = $container->get('entity_type.manager');
    return $parent_instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('system.site');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    // Load the configuration.
    return Yaml::parseFile($this->moduleHandler->getModule('ai_test')->getPath() . '/definitions/api_defaults.yml');
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    return [
      'gpt-test' => 'GPT Test',
      'gpt-awesome' => 'GPT Awesome',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'chat',
      'embeddings',
      'speech_to_text',
      'text_to_speech',
      'moderation',
      'image_classification',
      'text_classification',
      'echo',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    // First try to match the request with the requests to test.
    $matched_request = $this->getMatchingRequest('chat', $input);
    if ($matched_request) {
      if (!empty($matched_request['wait'])) {
        // If there is a wait time, sleep for that time.
        usleep($matched_request['wait'] * 1000);
      }
      // Return the response.
      return ChatOutput::fromArray($matched_request['response']);
    }
    $response = [];
    $normalized_input = '';
    if ($input instanceof ChatInput) {
      $normalized_input = $input->getMessages()[0]->getText();
    }

    if ($this->streamed) {
      $string = sprintf('Hello world! Input: %s. Config: %s.', $normalized_input ?? $input, json_encode($this->configuration));
      // Split the string every 10 characters to simulate streaming.
      $output = str_split($string, 10);
      $iterator = new MockIterator($output);
      $message = new MockStreamedChatIterator($iterator);
    }
    else {
      $message = new ChatMessage('user', sprintf('Hello world! Input: %s. Config: %s.', $normalized_input ?? $input, json_encode($this->configuration)));
    }

    if ($input instanceof ChatInput) {
      if ($input->getChatTools()) {
        $input_tools = $input->getChatTools();
        // Just give back the first function.
        $functions = $input_tools->getFunctions();
        $function = $functions[key($functions)];
        $output_function = new ToolsFunctionOutput($function, 'test');
        foreach ($function->getProperties() as $property) {
          // Give back different values depending on the property type.
          switch ($property->getType()) {
            case 'string':
              $value = 'test';
              break;

            case 'number':
              $value = 1;
              break;

            case 'boolean':
              $value = TRUE;
              break;

            case 'array':
              $value = ['test'];
              break;

            case 'object':
              $value = ['test' => 'test'];
              break;

            default:
              $value = 'test';
              break;
          }
          if ($property instanceof ToolsPropertyInput) {
            $output_function->addArgument(new ToolsPropertyResult($property, $value));
          }
        }

        $output_tools = new ToolsOutput($input_tools);
        $output_tools->setFunction($output_function);
        if ($message instanceof ChatMessage) {
          $message->setTools([$output_tools]);
        }
      }
    }
    // Mock an OpenAI response by default.
    $response = [
      'id' => 'chatcmpl-1234567890',
      'object' => 'chat.completion',
      'created' => time(),
      'model' => $model_id,
      'choices' => [
        [
          'index' => 0,
          'message' => [
            'role' => 'assistant',
            'content' => $message instanceof ChatMessage ? $message->getText() : 'Hello world! This is a mock response.',
          ],
          'finish_reason' => 'stop',
        ],
      ],
      'usage' => [
        'prompt_tokens' => 10,
        'completion_tokens' => 20,
        'total_tokens' => 30,
      ],
    ];
    return new ChatOutput($message, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function embeddings(EmbeddingsInput|string $input, string $model_id, array $tags = []): EmbeddingsOutput {
    $response = ['input' => sprintf('Hello world! %s', (string) $input)];

    return new EmbeddingsOutput($response, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsVectorSize(string $model_id): int {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function maxEmbeddingsInput($model_id = ''): int {
    return 1024;
  }

  /**
   * {@inheritdoc}
   */
  public function moderation(ModerationInput|string $input, ?string $model_id = NULL, array $tags = []): ModerationOutput {
    $response = [
      'input' => sprintf('Hello world! %s', (string) $input),
    ];
    $mod = new ModerationResponse(TRUE, $response);

    return new ModerationOutput($mod, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function speechToText(SpeechToTextInput|string $input, string $model_id, array $tags = []): SpeechToTextOutput {
    $response = [
      'input' => sprintf('Hello world! Input: %s. Config: %s', (string) $input, json_encode($this->configuration)),
    ];

    return new SpeechToTextOutput($response['input'], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToSpeech(TextToSpeechInput|string $input, string $model_id, array $tags = []): TextToSpeechOutput {
    $response = [
      'input' => sprintf('Hello world! %s', (string) $input),
    ];
    $audio = new AudioFile($response['input'], 'audio/mpeg', 'echoai.mp3');

    return new TextToSpeechOutput([$audio], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToImage(string|TextToImageInput $input, string $model_id, array $tags = []): TextToImageOutput {
    $image_file = new ImageFile();
    $folder = $this->moduleHandler->getModule('ai_test')->getPath() . '/assets/';
    $binary = file_get_contents($folder . 'image-1024x1024.png');
    $image_file->setBinary($binary);
    $image_file->setFilename('image-1024x1024.png');
    $image_file->setMimeType('image/png');
    return new TextToImageOutput([
      $image_file,
      $image_file,
    ], [
      'images' => [
        $binary,
        $binary,
      ],
    ], []);
  }

  /**
   * {@inheritdoc}
   */
  public function imageToImage(string|array|ImageToImageInput $input, string $model_id, array $tags = []): ImageToImageOutput {
    // Use the ImageToImageTrait to handle the image to image operation.
    return new ImageToImageOutput([$input->getImageFile()], [], []);
  }

  /**
   * {@inheritdoc}
   */
  public function imageClassification(string|array|ImageClassificationInput $input, string $model_id, array $tags = []): ImageClassificationOutput {
    $output = [];
    $response = [];
    if ($input instanceof ImageClassificationInput) {
      $labels = $input->getLabels();
      foreach ($labels as $label) {
        $output[] = new ImageClassificationItem($label, 0.5);
        $response[] = [
          'label' => $label,
          'confidence' => 0.5,
        ];
      }
    }

    return new ImageClassificationOutput($output, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textClassification(string|TextClassificationInput $input, string $model_id, array $tags = []): TextClassificationOutput {
    $output = [];
    $response = [];
    if ($input instanceof TextClassificationInput) {
      $labels = $input->getLabels();
      foreach ($labels as $label) {
        $output[] = new TextClassificationItem($label, 0.5);
        $response[] = [
          'label' => $label,
          'confidence' => 0.5,
        ];
      }
    }

    return new TextClassificationOutput($output, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function echo(string|EchoInput $input, string $model_id, array $options = []): EchoOutput {
    if (!$input instanceof EchoInput) {
      $input = new EchoInput($input);
    }
    return new EchoOutput((string) $input, ['echo' => (string) $input], []);
  }

  /**
   * Function to get a matching request from the modules.
   *
   * @param string $operation_type
   *   The operation type to check.
   * @param mixed $input
   *   The input to check against.
   *
   * @return array|null
   *   An array with the matching response, and wait microtime or NULL if no
   *   match is found.
   */
  public function getMatchingRequest(string $operation_type, mixed $input): ?array {
    // If its not an inputInterface, we cannot match it.
    if (!$input instanceof InputInterface) {
      return [];
    }
    // Get all the requests to test against.
    $requests = $this->dbRequestsToTest($operation_type);
    $requests = array_merge($requests, $this->testRequestsToTest($operation_type));
    foreach ($requests as $request) {
      $array = $input->toArray();
      // Look in the request message for added keys and add them to the request
      // to match against. This is important for new keys that are added to the
      // input, to not break older tests.
      if (isset($request['request']) && is_array($request['request'])
        && !empty($array['messages'])
        && isset($request['request']['messages']) && is_array($request['request']['messages'])) {
        foreach ($array['messages'][0] as $key => $value) {
          foreach ($request['request']['messages'] as $message_key => $message_value) {
            if (!array_key_exists($key, $message_value)) {
              $request['request']['messages'][$message_key][$key] = $value;
            }
            // Fix the order of the key in the messages.
            $request['request']['messages'][$message_key] = array_merge(
              array_intersect_key($array['messages'][0], $request['request']['messages'][$message_key]),
              $request['request']['messages'][$message_key]
            );
          }
        }
      }
      if (isset($request['request']) && is_array($request['request']) && Json::encode($request['request']) === Json::encode($array)) {
        // If the request matches, return the response.
        if (isset($request['response']) && is_array($request['response'])) {
          $response = $request['response'];
          // If there is a wait time, set it.
          $wait = isset($request['wait']) ? (int) $request['wait'] : 0;
          return [
            'response' => $response,
            'wait' => $wait,
          ];
        }
      }
    }
    return NULL;
  }

  /**
   * Function that will get all enabled tests.
   *
   * @param string $operation_type
   *   The operation type to check.
   *
   * @return array
   *   An array of all requests to try to match.
   */
  public function dbRequestsToTest(string $operation_type = 'chat'): array {
    // Verify that operation type is alphanumeric only.
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $operation_type)) {
      throw new \InvalidArgumentException('Operation type must be alphanumeric only.');
    }

    // Check so the entity type exists.
    if (!$this->entityTypeManager->hasDefinition('ai_mock_provider_result')) {
      return [];
    }

    // Load all the requests that are of operation type chat and enabled.
    /** @var \Drupal\ai_test\AIMockProviderResultInterface[] $entities */
    $entities = $this->entityTypeManager->getStorage('ai_mock_provider_result')
      ->loadByProperties([
        'operation_type' => $operation_type,
        'mock_enabled' => TRUE,
      ]);

    $responses = [];
    foreach ($entities as $entity) {
      $responses[] = [
        'request' => Yaml::parse($entity->get('request')->value),
        'response' => Yaml::parse($entity->get('response')->value),
        'wait' => $entity->get('sleep_time')->value,
      ];
    }

    return $responses;
  }

  /**
   * Function that will find the modules for example tests.
   *
   * @param string $operation_type
   *   The operation type to check.
   *
   * @return array
   *   An array of all requests to try to match.
   */
  public function testRequestsToTest(string $operation_type = 'chat'): array {
    // Verify that operation type is alphanumeric only.
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $operation_type)) {
      throw new \InvalidArgumentException('Operation type must be alphanumeric only.');
    }

    // Key is based on module list and operation type.
    $module_list = md5(implode('_', array_keys($this->moduleHandler->getModuleList())));
    // Create a cache key based on the operation type and module list.
    $cache_key = 'ai_test_requests_' . $operation_type . '_' . $module_list;
    // Check if the cache is already set.
    $cache = $this->cacheBackend->get($cache_key);
    if ($cache) {
      return $cache->data;
    }

    $requests = [];
    foreach ($this->moduleHandler->getModuleList() as $extension) {
      $path = $extension->getPath();
      $path_to_explore = $path . '/' . self::$requestTestSubDirectory . '/' . $operation_type;
      if (is_dir($path_to_explore)) {
        $files = scandir($path_to_explore);
        foreach ($files as $file) {
          // Make sure its yaml or yml file.
          if (preg_match('/\.(yaml|yml)$/', $file)) {
            $file_path = $path_to_explore . '/' . $file;
            if (is_file($file_path)) {
              $file_contents = file_get_contents($file_path);
              if ($file_contents !== FALSE) {
                $data = Yaml::parse($file_contents);
                if (!empty($data)) {
                  $requests[] = $data;
                }
              }
            }
          }
        }
      }
    }

    // Set the cache for the requests.
    $this->cacheBackend->set($cache_key, $requests, (time() + 60));

    return $requests;
  }

}
