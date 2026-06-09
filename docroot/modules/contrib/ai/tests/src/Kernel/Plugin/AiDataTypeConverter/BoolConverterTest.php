<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\AiDataTypeConverter;

use Drupal\KernelTests\KernelTestBase;

/**
 * This tests bool converter.
 *
 * @coversDefaultClass \Drupal\ai\Plugin\AiDataTypeConverter\BoolConverter
 *
 * @group ai
 */
class BoolConverterTest extends KernelTestBase {

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
  public function testBoolConverterApplicability(): void {
    $converter = $this->container->get('plugin.manager.ai_data_type_converter')->createInstance('boolean');
    // Check so it applied to data type.
    $this->assertTrue($converter->appliesToDataType('boolean')->applies());
    $converter_result = $converter->appliesToDataType('string');
    $this->assertFalse($converter_result->applies());
    $this->assertEquals($converter_result->getReason(), 'The data type is not a boolean.');
    $converter_result = $converter->appliesToDataType('array');
    $this->assertFalse($converter_result->applies());
    $this->assertEquals($converter_result->getReason(), 'The data type is not a boolean.');

    // Check so it applied to value.
    $this->assertTrue($converter->appliesToValue('boolean', TRUE)->applies());
    $this->assertTrue($converter->appliesToValue('boolean', FALSE)->applies());
    $this->assertTrue($converter->appliesToValue('boolean', 'true')->applies());
    $this->assertTrue($converter->appliesToValue('boolean', 'false')->applies());
    $this->assertTrue($converter->appliesToValue('boolean', '1')->applies());
    $this->assertTrue($converter->appliesToValue('boolean', '0')->applies());
    $converter_result = $converter->appliesToValue('boolean', 'string');
    $this->assertFalse($converter_result->applies());
    $this->assertEquals($converter_result->getReason(), 'The value cannot be converted to a boolean');
    $converter_result = $converter->appliesToValue('boolean', 123);
    $this->assertFalse($converter_result->applies());
    $this->assertEquals($converter_result->getReason(), 'The value cannot be converted to a boolean');
    $converter_result = $converter->appliesToValue('boolean', []);
    $this->assertFalse($converter_result->applies());
    $this->assertEquals($converter_result->getReason(), 'The value cannot be converted to a boolean');

    // Check so it converts.
    $this->assertTrue($converter->convert('boolean', TRUE));
    $this->assertFalse($converter->convert('boolean', FALSE));
    $this->assertTrue($converter->convert('boolean', 'true'));
    $this->assertFalse($converter->convert('boolean', 'false'));
    $this->assertTrue($converter->convert('boolean', '1'));
    $this->assertFalse($converter->convert('boolean', '0'));
    $this->assertTrue($converter->convert('boolean', 1));
    $this->assertFalse($converter->convert('boolean', 0));
    $this->assertTrue($converter->convert('boolean', 'TRUE'));
    $this->assertFalse($converter->convert('boolean', 'FALSE'));
  }

}
