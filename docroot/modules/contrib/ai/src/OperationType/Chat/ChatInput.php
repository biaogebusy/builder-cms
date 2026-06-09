<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\ai\Dto\HostnameFilterDto;
use Drupal\ai\Dto\StructuredOutputSchema;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use Drupal\ai\OperationType\InputBase;

/**
 * Input object for chat input.
 */
class ChatInput extends InputBase {

  /**
   * The message to convert to text.
   *
   * @var array
   */
  private array $messages;

  /**
   * The debug data.
   *
   * @var array
   */
  private array $debugData = [];

  /**
   * The tools input.
   *
   * @var \Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface|null
   */
  private ?ToolsInputInterface $chatTools = NULL;

  /**
   * The structured JSON schema.
   *
   * @var array
   */
  protected array $chatStructuredJsonSchema = [];

  /**
   * If strict schema exists, should it be followed.
   *
   * @var bool
   */
  protected bool $chatStrictSchema = FALSE;

  /**
   * If the output should be streamed or not.
   *
   * @var bool
   */
  protected bool $streamOutput = FALSE;

  /**
   * The system prompt.
   *
   * @var string
   */
  protected string $systemPrompt = '';

  /**
   * The hostname settings.
   *
   * @var \Drupal\ai\Dto\HostnameFilterDto|null
   */
  protected ?HostnameFilterDto $hostnameFilter = NULL;

  /**
   * The constructor.
   *
   * @param array $messages
   *   The messages to chat.
   */
  public function __construct(array $messages) {
    $this->messages = $messages;
  }

  /**
   * Get the messages to chat.
   *
   * @return array
   *   The messages.
   */
  public function getMessages(): array {
    return $this->messages;
  }

  /**
   * Set the messages to chat.
   *
   * @param array $messages
   *   The messages.
   */
  public function setMessages(array $messages) {
    $this->messages = $messages;
  }

  /**
   * Get if the output should be streamed or not.
   *
   * @return bool
   *   TRUE if the output should be streamed, FALSE otherwise.
   */
  public function isStreamedOutput(): bool {
    return $this->streamOutput;
  }

  /**
   * Set if the output should be streamed or not.
   *
   * @param bool $streamOutput
   *   TRUE if the output should be streamed, FALSE otherwise.
   */
  public function setStreamedOutput(bool $streamOutput): void {
    $this->setDebugDataValue('stream_output', $streamOutput);
    $this->streamOutput = $streamOutput;
  }

  /**
   * Set the system prompt.
   *
   * @param string $system_prompt
   *   The system prompt.
   */
  public function setSystemPrompt(string $system_prompt): void {
    $this->setDebugDataValue('system_prompt', $system_prompt);
    $this->systemPrompt = $system_prompt;
  }

  /**
   * Get the system prompt.
   *
   * @return string
   *   The system prompt.
   */
  public function getSystemPrompt(): string {
    return $this->systemPrompt;
  }

  /**
   * Sets the structured output schema for the chat response.
   *
   * Accepts either a StructuredOutputSchema DTO (recommended) or a plain array.
   * The schema tells the AI provider how to structure its response (e.g. JSON
   * with specific properties). Name and description are optional but useful for
   * provider compatibility (e.g. OpenAI); strict controls whether the schema
   * must be followed strictly.
   *
   * Using the DTO (recommended):
   * @code
   * use Drupal\ai\Dto\StructuredOutputSchema;
   *
   * $schema = new StructuredOutputSchema(
   *   name: 'my_schema',
   *   description: 'Optional description',
   *   strict: FALSE,
   *   json_schema: [
   *     'properties' => [
   *       'answer' => ['type' => 'string'],
   *     ],
   *   ],
   * );
   * $chatInput->setChatStructuredJsonSchema($schema);
   * @endcode
   *
   * Using an array (backwards compatible; same shape as DTO::toArray()):
   * @code
   * $chatInput->setChatStructuredJsonSchema([
   *   'name' => 'json_schema',
   *   'description' => NULL,
   *   'strict' => FALSE,
   *   'schema' => [
   *     'properties' => [
   *       'answer' => ['type' => 'string'],
   *     ],
   *   ],
   * ]);
   * @endcode
   *
   * The schema content may be passed under key 'schema' (default, documented)
   * or 'json_schema' (accepted for backwards compatibility). Stored and
   * returned value always uses key 'schema' for provider compatibility.
   *
   * @param array|\Drupal\ai\Dto\StructuredOutputSchema $schema
   *   The structured JSON schema as a DTO or an array with keys: name
   *   (optional, default "json_schema"), description (optional), strict
   *   (optional, default FALSE), and schema or json_schema (required,
   *   non-empty array).
   *
   * @throws \InvalidArgumentException
   *   If schema content is missing, not an array, or empty.
   */
  public function setChatStructuredJsonSchema(array|StructuredOutputSchema $schema): void {
    if ($schema instanceof StructuredOutputSchema) {
      // Backwards compatible: also set strict flag from object.
      // @phpstan-ignore-next-line method.deprecated
      $this->setChatStrictSchema($schema->isStrict());
      $schema = $schema->toArray();
    }
    // Accept 'schema' (default, documented) or 'json_schema'
    // for backwards compatibility.
    $content = $schema['schema'] ?? $schema['json_schema'] ?? [];
    if (!is_array($content) || $content === []) {
      $this->setDebugDataValue('chat_structured_json_schema', []);
      $this->chatStructuredJsonSchema = [];
      return;
    }
    $normalized = [
      'name' => isset($schema['name']) && is_string($schema['name']) && $schema['name'] !== '' ? $schema['name'] : 'json_schema',
      'description' => $schema['description'] ?? NULL,
      'strict' => array_key_exists('strict', $schema) ? (bool) $schema['strict'] : FALSE,
      'schema' => $content,
    ];
    $this->setDebugDataValue('chat_structured_json_schema', $normalized);
    $this->chatStructuredJsonSchema = $normalized;
  }

  /**
   * Gets the structured JSON schema (as stored for the provider).
   *
   * The returned array has keys name, description, strict, and schema (the
   * schema content). This shape matches the documented format used by most
   * providers (e.g. OpenAI response_format.json_schema).
   *
   * @return array
   *   The structured output array (name, description, strict, schema).
   */
  public function getChatStructuredJsonSchema(): array {
    return $this->chatStructuredJsonSchema;
  }

  /**
   * Set whether the chat should follow strict schema.
   *
   * @param bool $strict
   *   Whether to follow strict schema.
   *
   * @deprecated in ai:1.3.0 and is removed from ai:2.0.0. Set strict on the
   * StructuredOutputSchema passed to setChatStructuredJsonSchema() instead.
   * @see https://www.drupal.org/project/ai/issues/3548217
   */
  public function setChatStrictSchema(bool $strict): void {
    @trigger_error('ChatInput::setChatStrictSchema() is deprecated in ai:1.3.0 and is removed from ai:2.0.0. Set strict on setChatStructuredJsonSchema() DTO instead. See https://www.drupal.org/project/ai/issues/3548217', E_USER_DEPRECATED);
    $this->setDebugDataValue('chat_strict_schema', $strict);
    $this->chatStrictSchema = $strict;
  }

  /**
   * Get whether the chat should follow strict schema.
   *
   * @return bool
   *   Whether to follow strict schema.
   *
   * @deprecated in ai:1.3.0 and is removed from ai:2.0.0. Read strict from the
   * StructuredOutputSchema used with setChatStructuredJsonSchema().
   * @see https://www.drupal.org/project/ai/issues/3548217
   */
  public function getChatStrictSchema(): bool {
    @trigger_error('ChatInput::getChatStrictSchema() is deprecated in ai:1.3.0 and is removed from ai:2.0.0. Use the strict property on setChatStructuredJsonSchema() DTO instead. See https://www.drupal.org/project/ai/issues/3548217', E_USER_DEPRECATED);
    return $this->chatStrictSchema;
  }

  /**
   * Sets the tools input.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface $tools
   *   The tools input to set.
   */
  public function setChatTools(ToolsInputInterface $tools): void {
    $this->setDebugDataValue('chat_tools', $tools);
    $this->chatTools = $tools;
  }

  /**
   * Get the tools input.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface|null
   *   The tools input or NULL if not set.
   */
  public function getChatTools(): ?ToolsInputInterface {
    return $this->chatTools;
  }

  /**
   * Set the hostname filter settings.
   *
   * @param \Drupal\ai\Dto\HostnameFilterDto $hostnameFilter
   *   The hostname filter settings.
   */
  public function setHostnameFilter(HostnameFilterDto $hostnameFilter): void {
    $this->hostnameFilter = $hostnameFilter;
  }

  /**
   * Get the hostname filter settings.
   *
   * @return \Drupal\ai\Dto\HostnameFilterDto|null
   *   The hostname filter settings or NULL if not set.
   */
  public function getHostnameFilter(): ?HostnameFilterDto {
    return $this->hostnameFilter;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    $string = "";
    foreach ($this->messages as $message) {
      $string .= $message->getRole() . "\n" . $message->getText() . "\n";
    }
    return $string;
  }

  /**
   * Return the input as string.
   *
   * @return string
   *   The input as string.
   */
  public function __toString(): string {
    return $this->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $messages = [];
    foreach ($this->messages as $message) {
      $messages[] = $message->toArray();
    }
    return [
      'messages' => $messages,
      'debug_data' => $this->debugData,
      'chat_tools' => $this->chatTools ? $this->chatTools->renderToolsArray() : NULL,
      'chat_structured_json_schema' => $this->chatStructuredJsonSchema,
      'chat_strict_schema' => $this->chatStrictSchema,
    ];
  }

  /**
   * Create an instance from an array.
   *
   * @param array $data
   *   The data to create the instance from.
   *
   * @return static
   *   The created instance.
   */
  public static function fromArray(array $data): static {
    $messages = [];
    foreach ($data['messages'] ?? [] as $messageData) {
      $messages[] = ChatMessage::fromArray($messageData);
    }
    $instance = new static($messages);
    if (isset($data['debug_data'])) {
      $instance->setDebugData($data['debug_data']);
    }
    if (!empty($data['chat_tools'])) {
      // Iterate thought the functions.
      $input_functions = [];
      foreach ($data['chat_tools'] as $function) {
        $input_function = new ToolsFunctionInput($function['function']['name']);
        // Iterate through the properties and create the function input.
        if (isset($function['function']['parameters']['properties']) && is_array($function['function']['parameters']['properties'])) {
          $properties = [];
          foreach ($function['function']['parameters']['properties'] as $name => $propertyData) {
            $property = new ToolsPropertyInput($name);
            if (isset($propertyData['description'])) {
              $property->setDescription($propertyData['description']);
            }
            if (isset($propertyData['type'])) {
              $property->setType($propertyData['type']);
            }
            $properties[$name] = $property;
          }
          if (!empty($function['function']['parameters']['required']) && is_array($function['function']['parameters']['required'])) {
            foreach ($function['function']['parameters']['required'] as $requiredProperty) {
              if (isset($properties[$requiredProperty])) {
                $properties[$requiredProperty]->setRequired(TRUE);
              }
            }
          }
          $input_function->setProperties($properties);
        }
        if (isset($function['function']['description'])) {
          $input_function->setDescription($function['function']['description']);
        }
        $input_function->setName($function['function']['name']);
        $input_functions[] = $input_function;
      }
      $instance->setChatTools(
        new ToolsInput($input_functions),
      );
    }
    if (isset($data['chat_structured_json_schema'])) {
      $instance->setChatStructuredJsonSchema($data['chat_structured_json_schema']);
    }
    if (isset($data['chat_strict_schema'])) {
      // @phpstan-ignore-next-line method.deprecated
      $instance->setChatStrictSchema($data['chat_strict_schema']);
    }
    return $instance;
  }

}
