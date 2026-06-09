<?php

namespace Drupal\Tests\ai\Unit\OperationType\Chat;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use Drupal\ai\OperationType\GenericType\ImageFile;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the normalization of the ChatInput and ChatOutput works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\ChatInput
 */
class ChatArrayTest extends TestCase {

  /**
   * Test a very simple input from array.
   */
  public function testSimpleInputFromArray(): void {
    $input = new ChatInput([new ChatMessage('user', 'What is the weather today?')]);
    $array = $input->toArray();
    $this->assertIsArray($array);
    $this->assertArrayHasKey('messages', $array);
    $this->assertCount(1, $array['messages']);
    $this->assertEquals('user', $array['messages'][0]['role']);
    $this->assertEquals('What is the weather today?', $array['messages'][0]['text']);
    $this->assertArrayHasKey('debug_data', $array);
    $this->assertIsArray($array['debug_data']);
    $this->assertArrayHasKey('chat_tools', $array);
    $this->assertNull($array['chat_tools']);
    $this->assertArrayHasKey('chat_structured_json_schema', $array);
    $this->assertIsArray($array['chat_structured_json_schema']);
    $this->assertArrayHasKey('chat_strict_schema', $array);
    $this->assertFalse($array['chat_strict_schema']);
  }

  /**
   * Test to create a very simple input from array.
   */
  public function testSimpleInputFromArrayCreation(): void {
    $input = ChatInput::fromArray([
      'messages' => [
        [
          'role' => 'user',
          'text' => 'What is the weather today?',
          'images' => [],
          'tools' => NULL,
          'tool_id' => '',
        ],
      ],
      'debug_data' => [],
      'chat_tools' => NULL,
      'chat_strict_schema' => FALSE,
    ]);
    $this->assertInstanceOf(ChatInput::class, $input);
    $this->assertCount(1, $input->getMessages());
    $this->assertEquals('user', $input->getMessages()[0]->getRole());
    $this->assertEquals('What is the weather today?', $input->getMessages()[0]->getText());
    $this->assertEmpty($input->getMessages()[0]->getFiles());
    $this->assertNull($input->getChatTools());
    $this->assertEmpty($input->getChatStructuredJsonSchema());
    // @phpstan-ignore-next-line method.deprecated
    $this->assertFalse($input->getChatStrictSchema());
  }

  /**
   * Test to create a whole conversation from array.
   */
  public function testWholeConversationFromArray(): void {
    $input = ChatInput::fromArray([
      'messages' => [
        [
          'role' => 'user',
          'text' => 'What is the weather today?',
          'images' => [],
          'tools' => NULL,
          'tool_id' => '',
        ],
        [
          'role' => 'assistant',
          'text' => 'It is sunny and warm.',
          'images' => [],
          'tools' => NULL,
          'tool_id' => '',
        ],
      ],
      'debug_data' => [],
      'chat_tools' => NULL,
      'chat_strict_schema' => FALSE,
    ]);
    $this->assertCount(2, $input->getMessages());
    $this->assertEquals('user', $input->getMessages()[0]->getRole());
    $this->assertEquals('What is the weather today?', $input->getMessages()[0]->getText());
    $this->assertEquals('assistant', $input->getMessages()[1]->getRole());
    $this->assertEquals('It is sunny and warm.', $input->getMessages()[1]->getText());
    $this->assertEmpty($input->getMessages()[0]->getFiles());
    $this->assertEmpty($input->getMessages()[1]->getFiles());
    $this->assertNull($input->getChatTools());
    $this->assertEmpty($input->getChatStructuredJsonSchema());
    // @phpstan-ignore-next-line method.deprecated
    $this->assertFalse($input->getChatStrictSchema());
  }

  /**
   * Test adding a tool to the chat input.
   */
  public function testAddToolToChatInput(): void {
    $input = new ChatInput([new ChatMessage('user', 'What is the weather today?')]);
    $tool_spec = [
      'name' => 'Weather Tool',
      'description' => 'Provides weather information.',
      'parameters' => [
        'type' => 'object',
        'properties' => [
          'location' => [
            'name' => 'location',
            'type' => 'string',
            'description' => 'The location to get the weather for.',
          ],
        ],
        'required' => ['location'],
      ],
    ];
    $property = new ToolsPropertyInput('location');
    $property->setDescription('The location to get the weather for.');
    $property->setType('string');
    $property->setRequired(TRUE);
    $tool = new ToolsFunctionInput('weather_tools');
    $tool->setName('Weather Tool');
    $tool->setDescription('Provides weather information.');
    $tool->setProperty($property);
    $tool_input = new ToolsInput([$tool]);
    $input->setChatTools($tool_input);
    $array = $input->toArray();
    $this->assertNotNull($input->getChatTools());
    $this->assertEquals($tool_spec, $array['chat_tools'][0]['function']);
  }

  /**
   * Test adding a message with tool from an array.
   */
  public function testAddMessageWithToolFromArray(): void {
    $array = [
      'messages' => [
        [
          'role' => 'user',
          'text' => 'What is the weather today?',
          'images' => [],
          'tools' => NULL,
          'tool_id' => '',
        ],
      ],
      'debug_data' => [],
      'chat_tools' => [
        [
          'type' => 'function',
          'function' => [
            'name' => 'Weather Tool',
            'description' => 'Provides weather information.',
            'parameters' => [
              'type' => 'object',
              'properties' => [
                'location' => [
                  'name' => 'location',
                  'type' => 'string',
                  'description' => 'The location to get the weather for.',
                ],
              ],
              'required' => ['location'],
            ],
          ],
        ],
      ],
      'chat_strict_schema' => FALSE,
    ];
    $input = ChatInput::fromArray($array);
    $this->assertCount(1, $input->getMessages());
    $this->assertEquals('user', $input->getMessages()[0]->getRole());
    $this->assertEquals('What is the weather today?', $input->getMessages()[0]->getText());
    $this->assertEmpty($input->getMessages()[0]->getFiles());
    $this->assertNotNull($input->getChatTools());
    $this->assertEquals($array['chat_tools'], $input->getChatTools()->renderToolsArray());
  }

  /**
   * Test ChatMessage toArray with an attached image uses base64.
   */
  public function testChatMessageWithImageToArray(): void {
    $binaryData = 'fake image binary data';
    $mimeType = 'image/png';
    $filename = 'test.png';

    $image = new ImageFile($binaryData, $mimeType, $filename);
    $message = new ChatMessage('user', 'Describe this image');
    $message->setImage($image);

    $array = $message->toArray();

    $this->assertEquals('user', $array['role']);
    $this->assertEquals('Describe this image', $array['text']);
    $this->assertCount(1, $array['images']);
    $this->assertArrayHasKey('base64', $array['images'][0]);
    $this->assertArrayHasKey('mime_type', $array['images'][0]);
    $this->assertArrayHasKey('filename', $array['images'][0]);
    $this->assertArrayHasKey('type', $array['images'][0]);
    $this->assertEquals($mimeType, $array['images'][0]['mime_type']);
    $this->assertEquals($filename, $array['images'][0]['filename']);
    $this->assertEquals(ImageFile::class, $array['images'][0]['type']);
    // Verify it's base64 encoded with data URL scheme.
    $expectedBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($binaryData);
    $this->assertEquals($expectedBase64, $array['images'][0]['base64']);
  }

  /**
   * Test ChatMessage fromArray with base64 image data.
   */
  public function testChatMessageWithImageFromArray(): void {
    $binaryData = 'fake image binary data';
    $mimeType = 'image/jpeg';
    $filename = 'photo.jpg';
    $base64Data = 'data:' . $mimeType . ';base64,' . base64_encode($binaryData);

    $array = [
      'role' => 'user',
      'text' => 'What is in this photo?',
      'images' => [
        [
          'type' => ImageFile::class,
          'base64' => $base64Data,
          'mime_type' => $mimeType,
          'filename' => $filename,
        ],
      ],
      'tools' => NULL,
      'tool_id' => NULL,
    ];

    $message = ChatMessage::fromArray($array);

    $this->assertEquals('user', $message->getRole());
    $this->assertEquals('What is in this photo?', $message->getText());
    $this->assertCount(1, $message->getFiles());
    $this->assertCount(1, $message->getImages());

    $restoredImage = $message->getImages()[0];
    $this->assertInstanceOf(ImageFile::class, $restoredImage);
    $this->assertEquals($binaryData, $restoredImage->getBinary());
    $this->assertEquals($mimeType, $restoredImage->getMimeType());
    $this->assertEquals($filename, $restoredImage->getFilename());
  }

  /**
   * Test ChatMessage round-trip with image preserves data.
   */
  public function testChatMessageWithImageRoundTrip(): void {
    $binaryData = 'test image content for round trip';
    $mimeType = 'image/gif';
    $filename = 'animation.gif';

    // Create original message with image.
    $image = new ImageFile($binaryData, $mimeType, $filename);
    $originalMessage = new ChatMessage('user', 'Check this animation');
    $originalMessage->setImage($image);

    // Convert to array and back.
    $array = $originalMessage->toArray();
    $restoredMessage = ChatMessage::fromArray($array);

    // Verify all data is preserved.
    $this->assertEquals($originalMessage->getRole(), $restoredMessage->getRole());
    $this->assertEquals($originalMessage->getText(), $restoredMessage->getText());
    $this->assertCount(1, $restoredMessage->getImages());

    $restoredImage = $restoredMessage->getImages()[0];
    $this->assertEquals($binaryData, $restoredImage->getBinary());
    $this->assertEquals($mimeType, $restoredImage->getMimeType());
    $this->assertEquals($filename, $restoredImage->getFilename());
  }

  /**
   * Test ChatInput with image message toArray and fromArray.
   */
  public function testChatInputWithImageMessageRoundTrip(): void {
    $binaryData = 'sample image data';
    $mimeType = 'image/webp';
    $filename = 'sample.webp';

    $image = new ImageFile($binaryData, $mimeType, $filename);
    $message = new ChatMessage('user', 'Analyze this image');
    $message->setImage($image);

    $input = new ChatInput([$message]);
    $array = $input->toArray();

    // Verify the array structure.
    $this->assertCount(1, $array['messages']);
    $this->assertCount(1, $array['messages'][0]['images']);
    $this->assertArrayHasKey('base64', $array['messages'][0]['images'][0]);

    // Restore from array.
    $restoredInput = ChatInput::fromArray($array);

    $this->assertCount(1, $restoredInput->getMessages());
    $restoredMessage = $restoredInput->getMessages()[0];
    $this->assertEquals('user', $restoredMessage->getRole());
    $this->assertEquals('Analyze this image', $restoredMessage->getText());
    $this->assertCount(1, $restoredMessage->getImages());

    $restoredImage = $restoredMessage->getImages()[0];
    $this->assertEquals($binaryData, $restoredImage->getBinary());
    $this->assertEquals($mimeType, $restoredImage->getMimeType());
    $this->assertEquals($filename, $restoredImage->getFilename());
  }

  /**
   * Test ChatMessage with multiple images toArray.
   */
  public function testChatMessageWithMultipleImagesToArray(): void {
    $image1Binary = 'first image data';
    $image2Binary = 'second image data';

    $image1 = new ImageFile($image1Binary, 'image/png', 'first.png');
    $image2 = new ImageFile($image2Binary, 'image/jpeg', 'second.jpg');

    $message = new ChatMessage('user', 'Compare these images');
    $message->setImage($image1);
    $message->setImage($image2);

    $array = $message->toArray();

    $this->assertCount(2, $array['images']);
    $this->assertArrayHasKey('base64', $array['images'][0]);
    $this->assertArrayHasKey('base64', $array['images'][1]);
    $this->assertEquals('image/png', $array['images'][0]['mime_type']);
    $this->assertEquals('image/jpeg', $array['images'][1]['mime_type']);
  }

}
