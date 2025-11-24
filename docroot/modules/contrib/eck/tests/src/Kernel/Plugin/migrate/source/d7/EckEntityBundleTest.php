<?php

namespace Drupal\Tests\eck\Kernel\Plugin\migrate\source\d7;

use Drupal\Tests\migrate\Kernel\MigrateSqlSourceTestBase;

/**
 * Tests D7 ECK entity bundle source plugin.
 *
 * @covers \Drupal\eck\Plugin\migrate\source\d7\EckEntityBundle
 *
 * @group eck
 */
class EckEntityBundleTest extends MigrateSqlSourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['eck', 'migrate_drupal'];

  /**
   * {@inheritdoc}
   */
  public function providerSource() {
    $tests = [];

    // The source data.
    $tests[0]['database']['eck_bundle'] = [
      [
        'id' => '1',
        'machine_name' => 'simple_entity_simple_entity',
        'entity_type' => 'simple_entity',
        'name' => 'simple_entity',
        'label' => 'Simple entity',
        'config' => '[]',
      ],
      [
        'id' => '2',
        'machine_name' => 'complex_entity_complex_entity',
        'entity_type' => 'complex_entity',
        'name' => 'complex_entity',
        'label' => 'Complex entity',
        'config' => '{"managed_properties":{"title":0,"uid":0,"created":0,"changed":0,"language":0,"description":0},"multilingual":"1"}',
      ],
      [
        'id' => '3',
        'machine_name' => 'complex_entity_another_bundle',
        'entity_type' => 'complex_entity',
        'name' => 'another_bundle',
        'label' => 'Another bundle',
        'config' => '[]',
      ],
    ];

    $tests[0]['expected_results'] = $tests[0]['database']['eck_bundle'];
    return $tests;
  }

}
