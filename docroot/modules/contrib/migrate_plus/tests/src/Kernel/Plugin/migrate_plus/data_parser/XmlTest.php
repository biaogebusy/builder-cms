<?php

namespace Drupal\Tests\migrate_plus\Kernel\Plugin\migrate_plus\data_parser;

use Drupal\KernelTests\KernelTestBase;

/**
 * Test of the data_parser Xml migrate_plus plugin.
 *
 * @group migrate_plus
 */
class XmlTest extends KernelTestBase {

  protected static $modules = ['migrate', 'migrate_plus'];

  /**
   * Tests retrieving single value from element with attributes.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Exception
   */
  public function testSingleValue() {
    $path = $this->container
      ->get('module_handler')
      ->getModule('migrate_plus')
      ->getPath();
    $fileUrl = $path . '/tests/data/xml_data_parser.xml';
    $conf = [
      'plugin' => 'url',
      'data_fetcher_plugin' => 'file',
      'data_parser_plugin' => 'xml',
      'destination' => 'node',
      'urls' => [$fileUrl],
      'ids' => ['id' => ['type' => 'integer']],
      'fields' => [
        [
          'name' => 'id',
          'label' => 'Id',
          'selector' => 'id',
        ],
        [
          'name' => 'child',
          'label' => 'child',
          'selector' => 'children/child',
        ],
      ],
      'item_selector' => '/persons/person',
    ];

    /** @var \Drupal\migrate_plus\DataParserPluginManager $plugin_manager */
    $plugin_manager = $this->container->get('plugin.manager.migrate_plus.data_parser');
    $parser = $plugin_manager->createInstance('xml', $conf);

    $names = [];
    foreach ($parser as $item) {
      $names[] = (string) $item['child']->name;
    }

    $expectedNames = ['Elizabeth Junior', 'George Junior', 'Lucy'];
    $this->assertEquals($expectedNames, $names);
  }

}
