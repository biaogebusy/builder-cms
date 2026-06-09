<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\AiDataTypeConverter;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Yaml\Yaml;

/**
 * This tests yaml converter.
 *
 * @coversDefaultClass \Drupal\ai\Plugin\AiDataTypeConverter\YamlDeserializer
 *
 * @group ai
 */
class YamlDeserializerTest extends KernelTestBase {

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
  public function testYamlConverterApplicability(): void {
    $converter = $this->container->get('plugin.manager.ai_data_type_converter')->createInstance('yaml_deserializer');
    // Check so it applied to data type.
    $this->assertTrue($converter->appliesToDataType('list')->applies());
    $converter_result = $converter->appliesToDataType('string');
    $this->assertFalse($converter_result->applies());
    $this->assertEquals($converter_result->getReason(), '"string" data types should not be parsed as yaml');
    $converter_result = $converter->appliesToDataType('list');
    $this->assertTrue($converter_result->applies());

    // Check so it applied to value.
    $this->assertTrue($converter->appliesToValue('list', Yaml::dump(['test']))->applies());
    $this->assertTrue($converter->appliesToValue('list', Yaml::dump([
      'hello' => 'there',
      'obi-wan' => 'kenobi',
    ]))->applies());
    $converter_result = $converter->appliesToValue('list', 'string');
    $this->assertTrue($converter_result->valid());
    $converter_result = $converter->appliesToValue('string', Yaml::dump(['test']));
    $this->assertTrue($converter_result->valid());

    // Check so it converts.
    $array = $converter->convert('list', Yaml::dump(['test']));
    $this->assertIsArray($array);
    $this->assertEquals($array, ['test']);
    $object = $converter->convert('list', Yaml::dump([
      'hello' => 'there',
      'obi-wan' => 'kenobi',
    ]));
    $this->assertIsArray($object);
    $this->assertEquals($object['hello'], 'there');
    $this->assertEquals($object['obi-wan'], 'kenobi');
  }

}
