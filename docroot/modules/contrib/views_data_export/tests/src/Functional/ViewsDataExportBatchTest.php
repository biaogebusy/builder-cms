<?php

namespace Drupal\Tests\views_data_export\Functional;

use Drupal\csv_serialization\Encoder\CsvEncoder;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests views data export with batch.
 *
 * @group views_data_export
 */
class ViewsDataExportBatchTest extends ViewTestBase {

  use NodeCreationTrait;
  use ContentTypeCreationTrait;
  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'csv_serialization',
    'node',
    'file',
    'rest',
    'serialization',
    'user',
    'views',
    'views_data_export',
    'views_data_export_test',
    'search_api_test',
    'search_api_test_db',
    'search_api_test_example_content',
  ];

  /**
   * {@inheritdoc}
   */
  public static $testViews = [
    'search_api_tests',
    'views_data_test_1',
    'views_data_test_2',
    'views_data_test_3',
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
    foreach (range(0, 9) as $i) {
      $this->createNode([
        'status' => TRUE,
        'type' => 'page',
      ]);
      $this->addTestEntity($i + 1, [
        'name' => '',
        'body' => 'test test',
        'type' => 'entity_test_mulrev_changed',
        'keywords' => ['Orange', 'orange', 'örange', 'Orange'],
        'category' => 'item_category',
      ]);
    }
    $account = $this->drupalCreateUser(['access content', 'view test entity']);
    $this->drupalLogin($account);
  }

  /**
   * Test VDE SQL views with batch.
   */
  public function testBatchCreation() {

    // By this view we fetch page with link present.
    $this->drupalGet('views_data_export/test_1');
    $link = $this->getSession()->getPage()->findLink('here');
    $path_to_file = $link->getAttribute('href');
    $this->drupalGet($path_to_file);
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'File was not created');

    // By this view we obtain file right after batch process finished.
    // @todo - make separate FunctionalJavascript test to check automatic fetching.
    $this->drupalGet('views_data_export/test_2');
    $this->assertSession()->pageTextContainsOnce('automatically downloaded');

    // By this view's batch finished we must be redirected to /admin and fetch
    // csv with 3 rows only.
    $this->drupalGet('views_data_export/test_3');
    $this->assertEquals(parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH), $_SERVER['REQUEST_URI'] . 'admin',
    'User is not redirected to /admin page as expected');
    $link = $this->getSession()->getPage()->findLink('here');
    $path_to_file = $link->getAttribute('href');

    $path_to_file = parse_url($path_to_file, PHP_URL_PATH);
    $path_to_file = str_replace($_SERVER['REQUEST_URI'] . 'system/files', 'private:/', $path_to_file);
    $res3 = $this->readCsv(file_get_contents($path_to_file));
    // The first line always contains the header, expect one extra row.
    $this->assertEquals(4, count($res3), 'Count of exported nodes is wrong.');
    $this->assertEquals([0 => 'title'], $res3[0]);

    // Testing search api index's view.
    $this->indexItems('database_search_index');
    $this->drupalGet('views_data_export/test_search_api');

    $link = $this->getSession()->getPage()->findLink('here');
    $path_to_file = $link->getAttribute('href');
    $path_to_file = parse_url($path_to_file, PHP_URL_PATH);
    $path_to_file = str_replace($_SERVER['REQUEST_URI'] . 'system/files', 'private:/', $path_to_file);
    $res4 = $this->readCsv(file_get_contents($path_to_file));
    // The first line always contains the header, expect one extra row.
    $this->assertEquals(9, count($res4), 'Count of exported test entities is wrong.');
    $this->assertEquals([0 => 'id'], $res4[0]);
  }

  /**
   * Reading CSV content.
   *
   * @param string $content
   *   Content from file.
   *
   * @return array|mixed
   *   Array of CSV rows.
   */
  private function readCsv($content) {
    $csvEncoder = new CsvEncoder();
    return $csvEncoder->decode($content, '');
  }

}
