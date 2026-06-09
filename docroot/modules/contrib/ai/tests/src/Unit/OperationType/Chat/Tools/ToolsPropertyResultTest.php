<?php

namespace Drupal\Tests\ai\Unit\OperationType\Chat\Tools;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Drupal\ai\Exception\AiToolsValidationException;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResult;
use Drupal\ai\Service\HostnameFilter;

/**
 * Tests that the input works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResult
 */
class ToolsPropertyResultTest extends UnitTestCase {

  /**
   * Test the validate.
   *
   * @dataProvider typeProvider
   *
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResult::__construct
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResult::validate
   */
  public function testTyping($value, $type, $shouldFail) {
    // Mock on string or array.
    if (in_array(gettype($value), ['string', 'array']) && !empty($value)) {
      // Mock the service builder.
      $hostname_filter = $this->createMock(HostnameFilter::class);
      $hostname_filter->expects($this->once())
        ->method('filterText')
        ->willReturnCallback(fn($text) => $text);

      $container = new ContainerBuilder();
      $container->set('ai.hostname_filter_service', $hostname_filter);
      \Drupal::setContainer($container);
    }
    $stringInput = new ToolsPropertyInput('test_property', [
      'description' => 'Test description',
      'type' => $type,
    ]);
    // We set a integer value to test the validation.
    $result1 = new ToolsPropertyResult($stringInput, $value);
    // We expect an exception if its failing.
    if ($shouldFail) {
      $this->expectException(AiToolsValidationException::class);
      $result1->validate();
    }
    else {
      // Special cleaning of string boolean, since it handles that.
      if ($value === 'false') {
        $value = FALSE;
      }
      if ($value === 'true') {
        $value = TRUE;
      }
      // We expect the value to be the same.
      $this->assertEquals($value, $result1->getValue());
      $result1->validate();
    }
  }

  /**
   * Test minimum and maximum.
   *
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResult::__construct
   */
  public function testMinMax() {
    $intInput = new ToolsPropertyInput('test_property', [
      'description' => 'Test description',
      'type' => 'integer',
      'minimum' => 1,
      'maximum' => 10,
    ]);
    // We set a integer value to test the validation.
    $result1 = new ToolsPropertyResult($intInput, 5);
    $result1->validate();
    // We expect an exception if its failing.
    $this->expectException(AiToolsValidationException::class);
    $result2 = new ToolsPropertyResult($intInput, 0);
    $result2->validate();
    // We expect an exception if its failing.
    $this->expectException(AiToolsValidationException::class);
    $result3 = new ToolsPropertyResult($intInput, 11);
    $result3->validate();
  }

  /**
   * Test minimum and maximum length.
   *
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResult::__construct
   */
  public function testMinMaxLength() {
    // Mock the service builder.
    $hostname_filter = $this->createMock(HostnameFilter::class);
    // It doesn't run on empty.
    $hostname_filter->expects($this->exactly(2))
      ->method('filterText')
      ->willReturnCallback(fn($text) => $text);

    $container = new ContainerBuilder();
    $container->set('ai.hostname_filter_service', $hostname_filter);
    \Drupal::setContainer($container);

    $stringInput = new ToolsPropertyInput('test_property', [
      'description' => 'Test description',
      'type' => 'string',
      'minLength' => 1,
      'maxLength' => 10,
    ]);
    // We set a integer value to test the validation.
    $result1 = new ToolsPropertyResult($stringInput, 'test');
    $result1->validate();
    // We expect an exception if its failing.
    $this->expectException(AiToolsValidationException::class);
    $result2 = new ToolsPropertyResult($stringInput, '');
    $result2->validate();
    // We expect an exception if its failing.
    $this->expectException(AiToolsValidationException::class);
    $result3 = new ToolsPropertyResult($stringInput, 'aboriginalisms');
    $result3->validate();
  }

  /**
   * Test the formats.
   *
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResult::__construct
   */
  public function testFormat() {
    // Mock the service builder.
    $hostname_filter = $this->createMock(HostnameFilter::class);
    $hostname_filter->expects($this->exactly(2))
      ->method('filterText')
      ->willReturnCallback(fn($text) => $text);

    $container = new ContainerBuilder();
    $container->set('ai.hostname_filter_service', $hostname_filter);
    \Drupal::setContainer($container);

    // Email.
    $stringInput = new ToolsPropertyInput('test_property', [
      'description' => 'Test description',
      'type' => 'string',
      'format' => 'email',
    ]);
    // We set a integer value to test the validation.
    $result1 = new ToolsPropertyResult($stringInput, 'test@example.com');
    $result1->validate();
    $this->assertEquals('test@example.com', $result1->getValue());
    // We set a faulty email.
    $this->expectException(AiToolsValidationException::class);
    $result2 = new ToolsPropertyResult($stringInput, 'test(a)example.com');
    $result2->validate();

    // Url.
    $stringInput = new ToolsPropertyInput('test_property', [
      'description' => 'Test description',
      'type' => 'string',
      'format' => 'uri',
    ]);
    // We set a integer value to test the validation.
    $result1 = new ToolsPropertyResult($stringInput, 'http://example.com');
    $result1->validate();
    $this->assertEquals('http://example.com', $result1->getValue());
    // We set a faulty url.
    $this->expectException(AiToolsValidationException::class);
    $result2 = new ToolsPropertyResult($stringInput, 'example.com');

    // Date.
    $stringInput = new ToolsPropertyInput('test_property', [
      'description' => 'Test description',
      'type' => 'string',
      'format' => 'date',
    ]);
    // We set a integer value to test the validation.
    $result1 = new ToolsPropertyResult($stringInput, '2020-01-01');
    $result1->validate();
    $this->assertEquals('2020-01-01', $result1->getValue());
    // We set a faulty date.
    $this->expectException(AiToolsValidationException::class);
    $result2 = new ToolsPropertyResult($stringInput, '2020-01-32');

    // Date-time.
    $stringInput = new ToolsPropertyInput('test_property', [
      'description' => 'Test description',
      'type' => 'string',
      'format' => 'date-time',
    ]);
    // We set a integer value to test the validation.
    $result1 = new ToolsPropertyResult($stringInput, '2020-01-01T00:00:00Z');
    $result1->validate();
    $this->assertEquals('2020-01-01T00:00:00Z', $result1->getValue());
    // We set a faulty date-time.
    $this->expectException(AiToolsValidationException::class);
    $result2 = new ToolsPropertyResult($stringInput, '2020-01-32T00:00:00Z');
  }

  /**
   * Provides input values and tests that should break.
   *
   * @return array
   *   Value, type and if it should fail.
   */
  public static function typeProvider(): array {
    $types = [
      ['test', 'string', FALSE],
      [1, 'integer', FALSE],
      [4, 'int', FALSE],
      [1.1, 'float', FALSE],
      [TRUE, 'boolean', FALSE],
      [[], 'array', FALSE],
      [new \stdClass(), 'object', FALSE],
      [NULL, 'null', FALSE],
      [1, 'string', TRUE],
      ['test', 'integer', TRUE],
      ['test', 'float', TRUE],
      ['test', 'boolean', TRUE],
      ['test', 'array', TRUE],
      ['test', 'object', TRUE],
      [NULL, 'null', FALSE],
      ['null', 'null', TRUE],
    ];

    return $types;
  }

}
