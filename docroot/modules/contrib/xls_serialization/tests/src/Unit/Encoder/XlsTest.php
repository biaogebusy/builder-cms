<?php

namespace Drupal\Tests\xls_serialization\Unit\Encoder;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\xls_serialization\Encoder\Xls;
use Drupal\xls_serialization\XlsSerializationConstants;
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
   * The Excel encoder.
   *
   * @var \Drupal\xls_serialization\Encoder\Xls
   */
  private Xls $encoder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config = $this->prophesize(ImmutableConfig::class);
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('xls_serialization.configuration')->willReturn($config->reveal());
    $this->encoder = new Xls($config_factory->reveal());
  }

  /**
   * @covers ::supportsEncoding
   */
  public function testSupportsEncoding() {
    $this->assertTrue($this->encoder->supportsEncoding('xls'));
    $this->assertFalse($this->encoder->supportsEncoding('doc'));
  }

  /**
   * @covers ::validateWorksheetTitle
   */
  public function testValidateWorksheetTitle() {
    // Validate the conditions:
    // * Worksheet titles must not exceed 31 characters,
    // * contain:- ":" "\"  "/"  "?"  "*"  "["  "]".
    // cspell:disable-next-line
    $title = "Va\li[da*te: W\o/rk:she?et [T]/itle wi?th spe*cial ch[a]ract?ers";
    $expected_result = "Validate Worksheet Title with";
    // Use helper method to call protected 'validateWorksheetTitle'.
    $validateWorksheetTitle = self::getMethod('validateWorksheetTitle');
    $res = $validateWorksheetTitle->invokeArgs($this->encoder, [$title]);
    // Compare the result with the expected result.
    $this->assertEquals($expected_result, $res);
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
    $encoded = $this->encoder->encode($data, 'xlsx');

    // Load the file and verify the data.
    $file = $this->loadXlsFile($encoded);
    $sheet = $file->getSheet(0);
    // Verify headers.
    $this->assertEquals('foo', $sheet->getCell([1, 1])->getValue());
    $this->assertEquals('biz', $sheet->getCell([2, 1])->getValue());

    // Verify some of the data.
    $this->assertEquals('bar1', $sheet->getCell([1, 3])
      ->getValue());
    $this->assertEquals('baz2', $sheet->getCell([2, 4])
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
        'xls_format' => XlsSerializationConstants::EXCEL_2007_FORMAT,
        'strip_tags' => TRUE,
        'trim' => TRUE,
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

    $encoded = $this->encoder->encode([], 'xlsx', $context);
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
   * @covers ::formatValue
   */
  public function testFormatValue() {
    $encoder = $this->encoder;
    $format_value_method = new \ReflectionMethod($encoder, 'formatValue');
    $strip_tags_property = new \ReflectionProperty($encoder, 'stripTags');
    $trim_property = new \ReflectionProperty($encoder, 'trimValues');

    // Default value should be to strip tags and trim.
    $result = $format_value_method->invoke($encoder, '<p>HTML has been stripped &amp; trimmed</p> ');
    $this->assertEquals('HTML has been stripped & trimmed', $result);

    // Disable strip tags.
    $strip_tags_property->setValue($encoder, FALSE);
    $trim_property->setValue($encoder, TRUE);
    $result = $format_value_method->invoke($encoder, '<p>HTML has been retained &amp; trimmed</p> ');
    $this->assertEquals('<p>HTML has been retained &amp; trimmed</p>', $result);

    // Disable strip tags and trim.
    $strip_tags_property->setValue($encoder, FALSE);
    $trim_property->setValue($encoder, FALSE);
    $result = $format_value_method->invoke($encoder, '<p>HTML has been retained &amp; not trimmed</p> ');
    $this->assertEquals('<p>HTML has been retained &amp; not trimmed</p> ', $result);

    // Enable strip tags and disable trim.
    $strip_tags_property->setValue($encoder, TRUE);
    $trim_property->setValue($encoder, FALSE);
    $result = $format_value_method->invoke($encoder, '<p>HTML has been stripped &amp; not trimmed</p> ');
    $this->assertEquals('HTML has been stripped & not trimmed ', $result);

    // Enable strip tags and trim.
    $strip_tags_property->setValue($encoder, TRUE);
    $trim_property->setValue($encoder, TRUE);
    $result = $format_value_method->invoke($encoder, '<p>HTML has been stripped &amp; trimmed</p> ');
    $this->assertEquals('HTML has been stripped & trimmed', $result);
  }

  /**
   * Helper function to retrieve an xls object for a xls file.
   *
   * @param object $xls
   *   The xls file contents.
   * @param string $format
   *   The format the xls file is in. Defaults to 'xls'.
   *
   * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
   *   The excel object.
   */
  protected function loadXlsFile($xls, $format = 'xls') {
    // PHPExcel only supports files, so write the xls to a temporary file.
    $xls_file = @tempnam(File::sysGetTempDir(), 'xls_serialization' . $format);
    file_put_contents($xls_file, $xls);
    return IOFactory::load($xls_file);
  }

  /**
   * Helper method to allow testing protected methods.
   *
   * @param string $name
   *   The name of the protected method to test from the class:
   *   'Drupal\xls_serialization\Encoder\Xls'.
   *
   * @return \ReflectionMethod
   *   The protected method made public so it can be tested in test class.
   */
  protected static function getMethod($name) {
    $class = new \ReflectionClass('Drupal\xls_serialization\Encoder\Xls');
    $method = $class->getMethod($name);
    $method->setAccessible(TRUE);
    return $method;
  }

}
