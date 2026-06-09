<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\AiDataTypeConverter;

use Drupal\KernelTests\KernelTestBase;

/**
 * This tests json converter.
 *
 * @coversDefaultClass \Drupal\ai\Plugin\AiDataTypeConverter\JsonDeserializer
 *
 * @group ai
 */
class JsonDeserializerTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'key',
    'file',
    'system',
    'node',
    'media',
    'comment',
    'user',
  ];

  /**
   * Test the applicability.
   */
  public function testJsonConverterApplicability(): void {
    $converter = $this->container->get('plugin.manager.ai_data_type_converter')->createInstance('json_deserializer');
    // Check so it applied to data type.
    $this->assertTrue($converter->appliesToDataType('json_as_string')->applies());
    $converter_result = $converter->appliesToDataType('string');
    $this->assertFalse($converter_result->applies());
    $this->assertEquals($converter_result->getReason(), '"string" data types should not be parsed as json');
    $converter_result = $converter->appliesToDataType('list');
    $this->assertTrue($converter_result->applies());

    // Check so it applied to value.
    $this->assertTrue($converter->appliesToValue('list', json_encode(['test']))->applies());
    $this->assertTrue($converter->appliesToValue('list', json_encode([
      'hello' => 'there',
      'obi-wan' => 'kenobi',
    ]))->applies());
    $converter_result = $converter->appliesToValue('string', 'string');
    $this->assertFalse($converter_result->valid());
    $this->assertEquals($converter_result->getReason(), 'The value is not valid JSON');
    $converter_result = $converter->appliesToValue('list', json_encode(['test']));
    $this->assertTrue($converter_result->valid());

    // Check so it converts.
    $array = $converter->convert('list', json_encode(['test']));
    $this->assertIsArray($array);
    $this->assertEquals($array, ['test']);
    $object = $converter->convert('list', json_encode([
      'hello' => 'there',
      'obi-wan' => 'kenobi',
    ]));
    $this->assertIsArray($object);
    $this->assertEquals($object['hello'], 'there');
    $this->assertEquals($object['obi-wan'], 'kenobi');
  }

}
