<?php

namespace Drupal\Tests\views_data_export\Functional;

use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;
use Drupal\Tests\views\Functional\ViewTestBase;

/**
 * Tests views data export with batch.
 *
 * @group views_data_export
 */
class ViewsDataExportXmlTest extends ViewTestBase {

  use NodeCreationTrait;
  use ContentTypeCreationTrait;
  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'file',
    'rest',
    'serialization',
    'user',
    'views',
    'views_data_export',
    'views_data_export_test',
  ];

  /**
   * {@inheritdoc}
   */
  public static $testViews = [
    'test_xml_export',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['views_test_config']):void {
    parent::setUp($import_test_views, ['views_data_export_test']);
    $this->createContentType([
      'type' => 'page',
    ]);
    $account = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($account);
  }

  /**
   * Data provider for XML response tests.
   */
  public static function emptyXmlResponseProvider(): array {
    return [
      'default root' => [
        'test/data_export/xml/defaults',
        <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<response/>
XML,
      ],
      'custom root' => [
        'test/data_export/xml/change_node_names',
        <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nodes/>
XML,
      ],
    ];
  }

  /**
   * Test XML responses.
   *
   * @dataProvider emptyXmlResponseProvider
   */
  public function testEmptyXmlResponse(string $path, string $expected_xml): void {
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $actual_xml = $this->getSession()->getPage()->getContent();

    $this->assertSame(
      $expected_xml,
      $actual_xml,
      "The XML output matches for $path."
    );
  }

  /**
   * Data provider for XML response tests.
   */
  public static function normalXmlResponseProvider(): array {
    return [
      'default root' => [
        'test/data_export/xml/defaults',
        <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<response><item key="0"><title>page node title: 5</title></item><item key="1"><title>page node title: 4</title></item><item key="2"><title>page node title: 3</title></item><item key="3"><title>page node title: 2</title></item><item key="4"><title>page node title: 1</title></item><item key="5"><title>page node title: 0</title></item></response>
XML,
      ],
      'custom root' => [
        'test/data_export/xml/change_node_names',
        <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nodes><node key="0"><title>page node title: 5</title></node><node key="1"><title>page node title: 4</title></node><node key="2"><title>page node title: 3</title></node><node key="3"><title>page node title: 2</title></node><node key="4"><title>page node title: 1</title></node><node key="5"><title>page node title: 0</title></node></nodes>
XML,
      ],
    ];
  }

  /**
   * Test XML responses.
   *
   * @dataProvider normalXmlResponseProvider
   */
  public function testNormalXmlResponse(string $path, string $expected_xml): void {
    foreach (range(0, 5) as $i) {
      $this->createNode([
        'status' => TRUE,
        'type' => 'page',
        'title' => 'page node title: ' . $i,
        'created' => 280304046 + $i * 43200,
      ]);
    }

    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $actual_xml = $this->getSession()->getPage()->getContent();

    $this->assertSame(
      $expected_xml,
      $actual_xml,
      "The XML output matches for $path."
    );
  }

  /**
   * Data provider for XML response tests.
   */
  public static function changedEncodingXmlProvider(): array {
    return [
      'default root' => [
        'test/data_export/xml/no_encoding',
        <<<XML
<?xml version="1.0"?>
<response><item key="0"><title>page node title: 1</title></item><item key="1"><title>page node title: 0</title></item></response>
XML,
      ],
      'custom root' => [
        'test/data_export/xml/utf8_encoding',
        <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<response><item key="0"><title>page node title: 1</title></item><item key="1"><title>page node title: 0</title></item></response>
XML,
      ],
    ];
  }

  /**
   * Test XML responses.
   *
   * @dataProvider changedEncodingXmlProvider
   */
  public function testEncodingOptions(string $path, string $expected_xml): void {
    foreach (range(0, 1) as $i) {
      $this->createNode([
        'status' => TRUE,
        'type' => 'page',
        'title' => 'page node title: ' . $i,
        'created' => 280304046 + $i * 43200,
      ]);
    }

    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $actual_xml = $this->getSession()->getPage()->getContent();

    $this->assertSame(
      $expected_xml,
      $actual_xml,
      "The XML output matches for $path."
    );
  }

  /**
   * Data provider for XML response tests.
   */
  public static function batchedXmlEncodingProvider(): array {
    return [
      'default root' => [
        'test/data_export/xml/batched/no_encoding',
        <<<XML
<?xml version="1.0"?>
<response><item key="0"><title>page node title: 4</title></item><item key="1"><title>page node title: 3</title></item><item key="2"><title>page node title: 2</title></item>

<item key="0"><title>page node title: 1</title></item><item key="1"><title>page node title: 0</title></item></response>

XML,
      ],
      'custom root' => [
        'test/data_export/xml/batched/utf8_encoding',
        <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<response><item key="0"><title>page node title: 4</title></item><item key="1"><title>page node title: 3</title></item><item key="2"><title>page node title: 2</title></item>

<item key="0"><title>page node title: 1</title></item><item key="1"><title>page node title: 0</title></item></response>

XML,
      ],
    ];
  }

  /**
   * Test VDE XML views with batch.
   *
   * @dataProvider batchedXmlEncodingProvider
   */
  public function testBatchedXmlEncoding(string $views_path, string $expected_xml) {
    foreach (range(0, 4) as $i) {
      $this->createNode([
        'status' => TRUE,
        'type' => 'page',
        'title' => 'page node title: ' . $i,
        'created' => 280304046 + $i * 43200,
      ]);
    }

    // Fetch an XML file created with batching.
    $this->drupalGet($views_path);
    $link = $this->getSession()->getPage()->findLink('here');
    $path_to_file = $link->getAttribute('href');
    $this->drupalGet($path_to_file);
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'File was not created');

    $path_to_file = parse_url($path_to_file, PHP_URL_PATH);
    $public_directory_path = \Drupal::service('stream_wrapper_manager')->getViaScheme('public')->getDirectoryPath();
    $path_to_file = str_replace($_SERVER['REQUEST_URI'] . $public_directory_path, 'public:/', $path_to_file);
    $res1 = file_get_contents($path_to_file);

    $this->assertSame(
      $expected_xml,
      $res1,
      "The XML output matches for $views_path."
    );
  }

  /**
   * Data provider for XML response tests.
   */
  public static function prettyPrintingXmlProvider(): array {
    return [
      'pretty printing' => [
        'test/data_export/xml/pretty_printing',
        FALSE,
        <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<response>
  <item key="0">
    <title>page node title: 2</title>
  </item>
  <item key="1">
    <title>page node title: 1</title>
  </item>
  <item key="2">
    <title>page node title: 0</title>
  </item>
</response>
XML,
      ],
      'batched pretty printing' => [
        'test/data_export/xml/batched/pretty_printing',
        TRUE,
        <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<response>
  <item key="0">
    <title>page node title: 2</title>
  </item>



  <item key="0">
    <title>page node title: 1</title>
  </item>



  <item key="0">
    <title>page node title: 0</title>
  </item>
</response>

XML,
      ],
    ];
  }

  /**
   * Test XML responses.
   *
   * @dataProvider prettyPrintingXmlProvider
   */
  public function testPrettyPrinting(string $views_path, bool $batched, string $expected_xml): void {
    foreach (range(0, 2) as $i) {
      $this->createNode([
        'status' => TRUE,
        'type' => 'page',
        'title' => 'page node title: ' . $i,
        'created' => 280304046 + $i * 43200,
      ]);
    }

    if (!$batched) {
      $this->drupalGet($views_path);
      $this->assertSession()->statusCodeEquals(200);
      $actual_xml = $this->getSession()->getPage()->getContent();

      $this->assertSame(
        $expected_xml,
        $actual_xml,
        "The XML output matches for $views_path."
      );
    }
    else {
      // Fetch an XML file created with batching.
      $this->drupalGet($views_path);
      $link = $this->getSession()->getPage()->findLink('here');
      $path_to_file = $link->getAttribute('href');
      $this->drupalGet($path_to_file);
      $this->assertEquals(200, $this->getSession()->getStatusCode(), 'File was not created');

      $path_to_file = parse_url($path_to_file, PHP_URL_PATH);
      $public_directory_path = \Drupal::service('stream_wrapper_manager')->getViaScheme('public')->getDirectoryPath();
      $path_to_file = str_replace($_SERVER['REQUEST_URI'] . $public_directory_path, 'public:/', $path_to_file);
      $res1 = file_get_contents($path_to_file);

      $this->assertSame(
        $expected_xml,
        $res1,
        "The XML output matches for $views_path."
      );
    }
  }

}
