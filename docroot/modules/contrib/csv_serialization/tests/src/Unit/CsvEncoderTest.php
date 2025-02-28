<?php

namespace Drupal\Tests\csv_serialization\Unit;

use Drupal\csv_serialization\Encoder\CsvEncoder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the encoding and decoding functionality of CsvEncoder.
 *
 * @group test_example
 */
class CsvEncoderTest extends UnitTestCase {

  /**
   * The CSV encoder.
   *
   * @var \Drupal\csv_serialization\Encoder\CsvEncoder
   */
  public $encoder;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    $this->encoder = new CsvEncoder();
  }

  /**
   * Provides data for testing the encoder.
   *
   * @return array
   *   Am array of multi-dimensional arrays, to be converted to CSVs.
   */
  public function provideEncodeData() {
    $csv1_data = [
      // Row 1.
      [
        'title' => 'This is title 1',
        'body' => 'This is, body 1',
        'images' => ['img1.jpg'],
        'alias' => '',
        'status' => 1,
      ],
      // Row 2.
      [
        'title' => 'This is title 2',
        'body' => '<p>This is, body 2</p>',
        'images' => ['img1.jpg', 'img2.jpg'],
        'alias' => '',
        'status' => 0,
      ],
      // Row 3.
      [
        'title' => 'This is title 3',
        'body' => ['<p>This is, body 3</p>'],
        'images' => [
        [
          'src' => 'img1.jpg',
          'alt' => 'Image 1',
        ],
        [
          'src' => 'img2.jpg',
          'alt' => 'Image, 2',
        ],
        ],
        'alias' => '',
        'status' => 0,
      ],
    ];

    $csv_lf_encoded = trim(file_get_contents(__DIR__ . '/CsvEncoderTest.csv'));
    $csv_crlf_encoded = trim(file_get_contents(__DIR__ . '/CsvEncoderTestCRLF.csv'));

    return [
      [$csv1_data, $csv_lf_encoded, $csv_crlf_encoded],
    ];
  }

  /**
   * Provides data for testing the decoder.
   */
  public function provideDecodeData() {
    $csv1_data = [
      // Row 0. - (Headers)
      [
        'title',
        'body',
        'images',
        'alias',
        'status',
      ],
      // Row 1.
      [
        'This is title 1',
        'This is, body 1',
        'img1.jpg',
        '',
        1,
      ],
      // Row 2.
      [
        'This is title 2',
        'This is, body 2',
        ['img1.jpg', 'img2.jpg'],
        '',
        0,
      ],
      // Row 3.
      [
        'This is title 3',
        'This is, body 3',
        // Note that due to the flattening of multi-dimensional arrays
        // during encoding, this does not match Row 3 in provideEncodeData().
        [
          'img1.jpg',
          'Image 1',
          'img2.jpg',
          'Image, 2',
        ],
        '',
        0,
      ],
    ];

    $csv_lf_encoded = trim(file_get_contents(__DIR__ . '/CsvEncoderTest.csv'));
    $csv_crlf_encoded = trim(file_get_contents(__DIR__ . '/CsvEncoderTestCRLF.csv'));

    return [
      [$csv1_data, $csv_lf_encoded, $csv_crlf_encoded],
    ];
  }

  /**
   * Tests the CSV output of the encoder.
   *
   * @param array $csv_data
   *   Csv array data.
   * @param string $csv_encoded_lf
   *   Content of csv file with lf ending line.
   * @param string $csv_encoded_crlf
   *   Content of csv file with crlf ending line.
   *
   * @dataProvider provideEncodeData
   */
  public function testEncodeCsv(array $csv_data, $csv_encoded_lf, $csv_encoded_crlf): void {
    // @todo Test passing in arguments to the constructor. E.g., $separator, $enclosure, strip_tags, etc.
    // Note that what we encode does not exactly represent the hierarchy of
    // the data passed in. This is because cells containing multi-dimensional
    // arrays are flattened. Thus, encode($input) != decode($output).
    $this->assertEquals($csv_encoded_lf, $this->encoder->encode($csv_data, 'csv'));

    // Settings to test \r\n line ending files.
    $settings = [
      'delimiter' => ",",
      'enclosure' => '"',
      'escape_char' => "\\",
      'encoding' => "utf8",
      'strip_tags' => TRUE,
      'trim' => TRUE,
      'output_header' => TRUE,
      'new_line' => "\r\n",
    ];

    $this->encoder->setSettings($settings);
    $this->assertEquals($csv_encoded_crlf, $this->encoder->encode($csv_data, 'csv'));
  }

  /**
   * Tests the data structure created by decoding a CSV.
   *
   * @param array $csv_encoded
   *   Csv array data.
   * @param string $csv_data_lf
   *   Content of csv file with lf ending line.
   * @param string $csv_data_crlf
   *   Content of csv file with crlf ending line.
   *
   * @dataProvider provideDecodeData
   */
  public function testDecodeCsv(array $csv_encoded, $csv_data_lf, $csv_data_crlf) {
    $this->assertEquals($csv_encoded, $this->encoder->decode($csv_data_lf, 'csv'));
  }

}
