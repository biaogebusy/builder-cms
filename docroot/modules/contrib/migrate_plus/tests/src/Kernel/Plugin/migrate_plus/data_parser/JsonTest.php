<?php

namespace Drupal\Tests\migrate_plus\Kernel\Plugin\migrate_plus\data_parser;

use Drupal\KernelTests\KernelTestBase;

/**
 * Test of the data_parser Json migrate_plus plugin.
 *
 * @group migrate_plus
 */
class JsonTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['migrate', 'migrate_plus'];

  /**
   * Tests missing properties in json file.
   *
   * @param string $file
   *   File name in tests/data/ directory of this module.
   * @param array $ids
   *   Array of ids to pass to the plugin.
   * @param array $fields
   *   Array of fields to pass to the plugin.
   * @param array $expected
   *   Expected array from json decoded file.
   *
   * @dataProvider jsonBaseDataProvider
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Exception
   */
  public function testMissingProperties($file, array $ids, array $fields, array $expected): void {
    $path = $this->container
      ->get('module_handler')
      ->getModule('migrate_plus')
      ->getPath();
    $url = $path . '/tests/data/' . $file;

    /** @var \Drupal\migrate_plus\DataParserPluginManager $plugin_manager */
    $plugin_manager = $this->container
      ->get('plugin.manager.migrate_plus.data_parser');
    $conf = [
      'plugin' => 'url',
      'data_fetcher_plugin' => 'file',
      'data_parser_plugin' => 'json',
      'destination' => 'node',
      'urls' => [$url],
      'ids' => $ids,
      'fields' => $fields,
      'item_selector' => NULL,
    ];
    $json_parser = $plugin_manager->createInstance('json', $conf);

    $data = [];
    foreach ($json_parser as $item) {
      $data[] = $item;
    }

    $this->assertEquals($expected, $data);
  }

  /**
   * Provides multiple test cases for the testMissingProperty method.
   *
   * @return array
   *   The test cases.
   */
  public function jsonBaseDataProvider(): array {
    return [
      'missing properties' => [
        'file' => 'missing_properties.json',
        'ids' => ['id' => ['type' => 'integer']],
        'fields' => [
          [
            'name' => 'id',
            'label' => 'Id',
            'selector' => '/id',
          ],
          [
            'name' => 'title',
            'label' => 'Title',
            'selector' => '/title',
          ],
          [
            'name' => 'video_url',
            'label' => 'Video url',
            'selector' => '/video/url',
          ],
        ],
        'expected' => [
          [
            'id' => '1',
            'title' => 'Title',
            'video_url' => 'https://localhost/',
          ],
          [
            'id' => '2',
            'title' => '',
            'video_url' => 'https://localhost/',
          ],
          [
            'id' => '3',
            'title' => 'Title 3',
            'video_url' => '',
          ],
        ],
      ],
    ];
  }

}
