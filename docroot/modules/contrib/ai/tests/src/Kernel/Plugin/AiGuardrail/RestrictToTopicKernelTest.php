<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\AiGuardrail;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_test\Entity\AIMockProviderResult;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the RestrictToTopic guardrail end-to-end with a real provider.
 *
 * @group ai
 * @covers \Drupal\ai\Plugin\AiGuardrail\RestrictToTopic
 */
class RestrictToTopicKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ai_mock_provider_result');
  }

  /**
   * Reproduces the prompt the guardrail constructs from text and topics.
   *
   * Kept in lockstep with the heredoc in
   * \Drupal\ai\Plugin\AiGuardrail\RestrictToTopic::processInput().
   *
   * @param string $text
   *   The lowercased user message text.
   * @param string $topics_formatted
   *   Comma-joined topic list, lowercased.
   *
   * @return string
   *   The exact prompt the guardrail will send to the provider.
   */
  protected function buildExpectedPrompt(string $text, string $topics_formatted): string {
    return <<<PROMPT
Given a text and a list of topics, return a valid json list of which topics are present in the text. If none, just return an empty list. Don't format the output in any other way, just return the list as JSON inside a ```json code block.

Output example when not finding anything:
-------------
```json
{"topics_present": []}
```

Output example when finding something relevant:
--------------
```json
{"topics_present": ["topic_4", "topic_6"]}
```

Text:
----
"$text"

Relevant Topics you can pick from:
------
$topics_formatted

Result:
------
PROMPT;
  }

  /**
   * Registers a mock chat response keyed by the prompt the guardrail builds.
   *
   * @param string $text
   *   The user message text that will be embedded in the prompt.
   * @param string $topics_formatted
   *   The comma-joined topic list embedded in the prompt.
   * @param string $response
   *   The assistant text the mock provider should return.
   */
  protected function registerMockResponse(string $text, string $topics_formatted, string $response): void {
    $prompt = $this->buildExpectedPrompt($text, $topics_formatted);
    AIMockProviderResult::create([
      'label' => 'RestrictToTopic test response',
      'operation_type' => 'chat',
      'mock_enabled' => TRUE,
      'request' => Yaml::dump([
        'messages' => [
          [
            'role' => 'user',
            'text' => $prompt,
            'images' => [],
            'tools' => NULL,
            'tool_id' => NULL,
          ],
        ],
        'debug_data' => [],
        'chat_tools' => NULL,
        'chat_structured_json_schema' => [],
        'chat_strict_schema' => FALSE,
      ]),
      'response' => Yaml::dump([
        'normalized' => [
          'role' => 'assistant',
          'text' => $response,
          'images' => [],
          'tools' => NULL,
          'tool_id' => NULL,
        ],
        'rawOutput' => [],
        'metadata' => [],
        'tokenUsage' => [
          'input' => NULL,
          'output' => NULL,
          'total' => NULL,
          'reasoning' => NULL,
          'cached' => NULL,
        ],
      ]),
    ])->save();
  }

  /**
   * Builds a configured guardrail plugin instance.
   */
  protected function createGuardrail(array $configuration): object {
    $plugin_manager = \Drupal::service('plugin.manager.ai_guardrail');
    return $plugin_manager->createInstance('restrict_to_topic', $configuration + [
      'llm_provider' => 'echoai',
      'llm_model' => 'gpt-test',
      'llm_config' => [],
      'invalid_topics_present_message' => 'invalid topic present',
      'valid_topics_missing_message' => 'no valid topic found',
    ]);
  }

  /**
   * When the provider returns a non-JSON response, the guardrail stops.
   *
   * Covers the branch introduced by issue #3586469 — previously a NULL
   * json_decode() result caused a fatal "attempt to read property on null".
   */
  public function testStopResultWhenResponseIsNotJson(): void {
    $this->registerMockResponse('I want to talk about sports', 'sports', 'I cannot help with that request.');
    $guardrail = $this->createGuardrail(['valid_topics' => 'sports']);

    $result = $guardrail->processInput(new ChatInput([
      new ChatMessage('user', 'I want to talk about sports'),
    ]));

    $this->assertInstanceOf(StopResult::class, $result);
    $this->assertEquals('Could not decode the AI response as JSON.', $result->getMessage());
  }

  /**
   * A response containing a valid topic passes the guardrail.
   */
  public function testPassWhenValidTopicFound(): void {
    $this->registerMockResponse('I love sports', 'sports', '{"topics_present":["sports"]}');
    $guardrail = $this->createGuardrail(['valid_topics' => 'sports']);

    $result = $guardrail->processInput(new ChatInput([
      new ChatMessage('user', 'I love sports'),
    ]));

    $this->assertInstanceOf(PassResult::class, $result);
  }

  /**
   * A response containing an invalid topic stops with the configured message.
   */
  public function testStopWhenInvalidTopicFound(): void {
    $this->registerMockResponse('tell me about weapons', 'sports,weapons', '{"topics_present":["weapons"]}');
    $guardrail = $this->createGuardrail([
      'valid_topics' => 'sports',
      'invalid_topics' => 'weapons',
    ]);

    $result = $guardrail->processInput(new ChatInput([
      new ChatMessage('user', 'tell me about weapons'),
    ]));

    $this->assertInstanceOf(StopResult::class, $result);
    $this->assertEquals('invalid topic present', $result->getMessage());
  }

  /**
   * An empty topics_present array stops with the missing-valid-topic message.
   */
  public function testStopWhenValidTopicMissing(): void {
    $this->registerMockResponse('tell me about literature', 'sports', '{"topics_present":[]}');
    $guardrail = $this->createGuardrail(['valid_topics' => 'sports']);

    $result = $guardrail->processInput(new ChatInput([
      new ChatMessage('user', 'tell me about literature'),
    ]));

    $this->assertInstanceOf(StopResult::class, $result);
    $this->assertEquals('no valid topic found', $result->getMessage());
  }

  /**
   * The decoder strips ```json fences from the provider response.
   *
   * Confirms the integration with the ai.prompt_json_decode service handles
   * the common case of models still wrapping JSON in markdown fences.
   */
  public function testDecoderHandlesFencedJson(): void {
    $fenced = "```json\n{\"topics_present\":[\"sports\"]}\n```";
    $this->registerMockResponse('sports talk', 'sports', $fenced);
    $guardrail = $this->createGuardrail(['valid_topics' => 'sports']);

    $result = $guardrail->processInput(new ChatInput([
      new ChatMessage('user', 'sports talk'),
    ]));

    $this->assertInstanceOf(PassResult::class, $result);
  }

}
