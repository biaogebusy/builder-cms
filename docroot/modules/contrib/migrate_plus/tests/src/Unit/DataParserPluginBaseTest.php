<?php

namespace Drupal\Tests\migrate_plus\Unit;

use Drupal\migrate_plus\DataParserPluginBase;
use Drupal\Tests\migrate\Unit\MigrateTestCase;

/**
 * @coversDefaultClass \Drupal\migrate_plus\DataParserPluginBase
 *
 * @group migrate_plus
 */
class DataParserPluginBaseTest extends MigrateTestCase {

  /**
   * @covers ::nextSource
   */
  public function testNextSourceWithOneUrl(): void {
    $parser = $this->getMockedDataParser();
    $parser->expects($this->once())
      ->method('openSourceUrl')
      ->willReturn(TRUE);
    $this->assertTrue($parser->nextSource());
  }

  /**
   * @covers ::nextSource
   */
  public function testNextSourceWithoutUrls(): void {
    $config = [
      'urls' => [],
    ];

    $parser = $this->getMockedDataParser($config);
    $parser->expects($this->never())
      ->method('openSourceUrl');
    $this->assertFalse($parser->nextSource());
  }

  /**
   * @covers ::count
   */
  public function testCountWithoutUrls(): void {
    $config = [
      'urls' => [],
    ];

    $parser = $this->getMockedDataParser($config);
    $parser->expects($this->never())
      ->method('openSourceUrl');
    $this->assertEquals(0, $parser->count());
  }

  /**
   * Returns a mocked data parser.
   *
   * @param array $configuration
   *   The configuration to pass to the data parser.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\Tests\migrate_plus\Unit\DataParserPluginBaseMock
   *   An mock instance of DataParserPluginBase.
   */
  protected function getMockedDataParser(array $configuration = []) {
    // Set constructor arguments.
    $configuration += [
      'urls' => ['http://example.org/data_parser_test'],
      'item_selector' => 0,
    ];
    $plugin_id = 'foo';
    $plugin_definition = [
      'id' => 'foo',
      'title' => 'Foo',
    ];

    return $this->getMockBuilder(DataParserPluginBaseMock::class)
      ->setConstructorArgs([$configuration, $plugin_id, $plugin_definition])
      ->setMethods(['openSourceUrl'])
      ->getMockForAbstractClass();
  }

}

/**
 * Mock for abstract class DataParserPluginBase.
 *
 * This mock is used to make certain methods publicly accessible.
 */
abstract class DataParserPluginBaseMock extends DataParserPluginBase {

  /**
   * {@inheritdoc}
   *
   * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
   */
  public function nextSource() {
    return parent::nextSource();
  }

}
