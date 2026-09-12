<?php

namespace Drupal\Tests\xls_serialization_open_spout\Unit\Encoder;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\xls_serialization\Unit\Encoder\XlsTest;
use Drupal\xls_serialization_open_spout\Encoder\OpenSpoutXlsxEncoder;

/**
 * Tests the XLS encoder with the OpenSpout adapter.
 *
 * @group xls_serialization
 *
 * @coversDefaultClass \Drupal\xls_serialization\Encoder\Xls
 */
class OpenSpoutXlsxEncoderTest extends XlsTest {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config = $this->prophesize(ImmutableConfig::class);
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('xls_serialization.configuration')->willReturn($config->reveal());

    $fileSystem = $this->getMockBuilder(FileSystemInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $fileSystem
      ->expects($this->any())
      ->method('tempnam')
      ->willReturnCallback(function ($path) {
        return tempnam(sys_get_temp_dir(), 'xls_serialization');
      });
    $fileSystem
      ->expects($this->any())
      ->method('realpath')
      ->willReturnCallback('realpath');
    $fileSystem
      ->expects($this->any())
      ->method('unlink')
      ->willReturnCallback('unlink');

    $this->encoder = new OpenSpoutXlsxEncoder($config_factory->reveal(), $fileSystem);
  }

  /**
   * @covers ::supportsEncoding
   */
  public function testSupportsEncoding() {
    // Does not support xls - only xlsx.
    $this->assertTrue($this->encoder->supportsEncoding('xlsx'));
    $this->assertFalse($this->encoder->supportsEncoding('xls'));
    $this->assertFalse($this->encoder->supportsEncoding('doc'));
  }

  /**
   * {@inheritdoc}
   */
  public function testEncodeMetaData() {
    $this->markTestSkipped('Metadata is not supported.');
  }

}
