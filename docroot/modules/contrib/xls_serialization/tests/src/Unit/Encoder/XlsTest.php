<?php

namespace Drupal\Tests\xls_serialization\Unit\Encoder;

use Drupal\Tests\UnitTestCase;
use Drupal\xls_serialization\Encoder\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\File;

/**
 * Tests the XLS encoder.
 *
 * @group xls_serialization
 *
 * @coversDefaultClass \Drupal\xls_serialization\Encoder\Xls
 */
class XlsTest extends UnitTestCase {

  /**
   * @covers ::supportsEncoding
   */
  public function testSupportsEncoding() {
    $encoder = new Xls();
    $this->assertTrue($encoder->supportsEncoding('xls'));
    $this->assertFalse($encoder->supportsEncoding('doc'));
  }

  /**
   * @covers ::encode
   */
  public function testEncode() {
    $data = [
      ['foo' => 'bar', 'biz' => 'baz'],
      ['foo' => 'bar1', 'biz' => 'baz1'],
      ['foo' => 'bar2', 'biz' => 'baz2'],
    ];
    $encoder = new Xls();
    $encoded = $encoder->encode($data, 'xlsx');

    // Load the file and verify the data.
    $file = $this->loadXlsFile($encoded);
    $sheet = $file->getSheet(0);
    // Verify headers.
    $this->assertEquals('foo', $sheet->getCellByColumnAndRow(1, 1)->getValue());
    $this->assertEquals('biz', $sheet->getCellByColumnAndRow(2, 1)->getValue());

    // Verify some of the data.
    $this->assertEquals('bar1', $sheet->getCellByColumnAndRow(1, 3)
      ->getValue());
    $this->assertEquals('baz2', $sheet->getCellByColumnAndRow(2, 4)
      ->getValue());
  }

  /**
   * Tests metadata.
   *
   * @covers ::encode
   */
  public function testEncodeMetaData() {
    // Test metadata.
    $style_plugin = new \stdClass();
    $style_plugin->options = [
      'xls_settings' => [
        'xls_format' => 'Excel2007',
        'metadata' => [
          'creator' => 'J Author',
          'last_modified_by' => 'That one guy, down the hall?',
          'created' => 1320998400,
          'modified' => 1355299200,
          'title' => 'A fantastic title. The best title.',
          'description' => 'Such a great description. Everybody is saying.',
          'subject' => 'This spreadsheet is about numbers.',
          'keywords' => 'testing xls spreadsheets',
          'category' => 'test category',
          'manager' => 'J Q Manager',
          'company' => 'Drupal',
          'custom_properties' => [
            'foo' => 'bar',
            'biz' => [12345.12, 'f'],
            'baz' => [1320998400, 'd'],
          ],
        ],
      ],
    ];
    $context['views_style_plugin'] = $style_plugin;

    $encoder = new Xls();
    $encoded = $encoder->encode([], 'xlsx', $context);
    $file = $this->loadXlsFile($encoded, 'xlsx');
    $metadata = $style_plugin->options['xls_settings']['metadata'];
    $properties = $file->getProperties();
    $this->assertEquals($metadata['creator'], $properties->getCreator());
    $this->assertEquals($metadata['last_modified_by'], $properties->getLastModifiedBy());
    $this->assertEquals($metadata['created'], $properties->getCreated());
    $this->assertEquals($metadata['modified'], $properties->getModified());
    $this->assertEquals($metadata['title'], $properties->getTitle());
    $this->assertEquals($metadata['description'], $properties->getDescription());
    $this->assertEquals($metadata['subject'], $properties->getSubject());
    $this->assertEquals($metadata['keywords'], $properties->getKeywords());
    $this->assertEquals($metadata['category'], $properties->getCategory());
    $this->assertEquals($metadata['manager'], $properties->getManager());
    $this->assertEquals($metadata['company'], $properties->getCompany());

    // Verify custom properties.
    $this->assertEquals('bar', $properties->getCustomPropertyValue('foo'));
    $this->assertEquals('12345.12', $properties->getCustomPropertyValue('biz'));
    $this->assertEquals('1320998400', $properties->getCustomPropertyValue('baz'));
  }

  /**
   * Helper function to retrieve an xls object for a xls file.
   *
   * @param object $xls
   *   The xls file contents.
   * @param string $format
   *   The format the xls file is in. Defaults to 'xls'.
   *
   * @return \PHPExcel
   *   The excel object.
   */
  protected function loadXlsFile($xls, $format = 'xls') {
    // PHPExcel only supports files, so write the xls to a temporary file.
    $xls_file = @tempnam(File::sysGetTempDir(), 'phpxltmp.' . $format);
    file_put_contents($xls_file, $xls);
    return IOFactory::load($xls_file);
  }

}
