<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ReplayedChatMessageIterator;
use Drupal\ai\OperationType\Chat\StreamedChatMessage;

/**
 * Tests the PromptCodeBlockExtractor service.
 *
 * @group ai
 */
final class PromptCodeBlockExtractorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai'];

  /**
   * The instance of the service under test.
   *
   * @var \Drupal\ai\Service\PromptCodeBlockExtractor
   */
  protected $extractor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Instantiate the extractor service.
    $this->extractor = $this->container->get('ai.prompt_code_block_extractor');
  }

  /**
   * Test extractPayload method with valid input for different block types.
   *
   * @dataProvider payloadDataProvider
   */
  public function testExtractPayload($input, $code_block_type, $expected): void {
    $result = $this->extractor->extractPayload($input, $code_block_type);
    self::assertEquals($expected, $result);
  }

  /**
   * Data provider for extractPayload tests.
   */
  public static function payloadDataProvider(): array {
    return [
      ['```html' . PHP_EOL . '<div>Hello World</div>```', 'html', '<div>Hello World</div>'],
      ['```<block>Any Type Block</block>```', 'html', 'Any Type Block'],
      ['```yaml' . PHP_EOL . 'data: value```', 'yaml', 'data: value'],
      ['```json' . PHP_EOL . '{"key": "value"}```', 'json', '{"key": "value"}'],
      ['```css' . PHP_EOL . 'body { background-color: white; }```', 'css', 'body { background-color: white; }'],
      ['No code block here.', 'html', NULL],
    ];
  }

  /**
   * Test extract method with valid ChatMessage input for different block types.
   *
   * @dataProvider chatMessageDataProvider
   */
  public function testExtractChatMessage($role, $text, $code_block_type, $expected): void {
    $chat_message = new ChatMessage($role, $text);
    $result = $this->extractor->extract($chat_message, $code_block_type);

    if ($expected !== NULL) {
      self::assertEquals($expected, $result);
    }
    else {
      self::assertInstanceOf(ChatMessage::class, $result);
    }
  }

  /**
   * Data provider for extractChatMessage tests.
   */
  public static function chatMessageDataProvider(): array {
    return [
      ['user', '```html' . PHP_EOL . '<div>Hello World</div>```', 'html', '<div>Hello World</div>'],
      ['user', 'No code block here.', 'html', NULL],
      ['assistant', '```json' . PHP_EOL . '{"key": "value"}```', 'json', '{"key": "value"}'],
    ];
  }

  /**
   * Test extract method with valid string input for different block types.
   *
   * @dataProvider stringDataProvider
   */
  public function testExtractString($input, $code_block_type, $expected): void {
    $result = $this->extractor->extract($input, $code_block_type);

    if ($expected !== NULL) {
      self::assertEquals($expected, $result);
    }
    else {
      // If no code block return the original input.
      self::assertEquals($input, $result);
    }
  }

  /**
   * Data provider for extractString tests.
   */
  public static function stringDataProvider(): array {
    return [
      ['This is a plain text.', 'html', NULL],
      [
        '```css' . PHP_EOL . 'body { background-color: white; }```',
        'css', 'body { background-color: white; }',
      ],
    ];
  }

  /**
   * Test extract method with valid Streaming input for different block types.
   *
   * @dataProvider streamingDataProvider
   */
  public function testExtractStreamingInput($input, $code_block_type, $expected): void {
    // Create a mock StreamedChatMessageIterator and populate it
    // with StreamedChatMessage.
    $stream = new ReplayedChatMessageIterator(new \ArrayObject([
      new StreamedChatMessage('user', $input[0]),
      new StreamedChatMessage('assistant', $input[1]),
    ]));
    // Empty first message in order to prevent iterator to duplicate.
    // @todo is there another way?
    $stream->setFirstMessage('');

    $result = $this->extractor->extract($stream, $code_block_type);

    if ($expected !== NULL) {
      self::assertEquals($expected, $result);
    }
    else {
      // If no code block return the original input.
      self::assertInstanceOf(ReplayedChatMessageIterator::class, $result);
    }
  }

  /**
   * Data provider for extractStreamingInput tests.
   */
  public static function streamingDataProvider(): array {
    return [
      [
        [
          '```html' . PHP_EOL . '<div>Line 1</div>', '<div>Line 2</div>```',
        ],
        'html',
        '<div>Line 1</div><div>Line 2</div>',
      ],
      [
        ['```css' . PHP_EOL . 'body { background-color: white; }',
          'div { background-color: white }```',
        ],
        'css',
        'body { background-color: white; }div { background-color: white }',
      ],
      [['No code block here', 'No code block here.'], 'html', NULL],
    ];
  }

}
