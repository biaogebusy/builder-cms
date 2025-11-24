<?php

namespace Drupal\Tests\eck\Kernel\Plugin\migrate\source\d7;

use Drupal\Tests\migrate\Kernel\MigrateSqlSourceTestBase;

/**
 * Tests D7 ECK entity type source plugin.
 *
 * @covers \Drupal\eck\Plugin\migrate\source\d7\EckEntityType
 *
 * @group eck
 */
class EckEntityTypeTest extends MigrateSqlSourceTestBase {

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
    $tests[0]['database']['eck_entity_type'] = [
      [
        'id' => '2',
        'name' => 'simple',
        'label' => 'Simple',
        'properties' => json_encode(
          (object) [
            'title' => (object) [
              'label' => 'Title',
              'type' => 'text',
              'behavior' => 'title',
            ],
            'uid' => (object) [
              'label' => 'Author',
              'type' => 'integer',
              'behavior' => 'author',
            ],
            'created' => (object) [
              'label' => 'Created',
              'type' => 'integer',
              'behavior' => 'created',
            ],
            'changed' => (object) [
              'label' => 'Changed',
              'type' => 'integer',
              'behavior' => 'changed',
            ],
          ]),
      ],
      [
        'id' => '2',
        'name' => 'complex',
        'label' => 'Complex',
        'properties' => json_encode(
          (object) [
            'title' => (object) [
              'label' => 'Title',
              'type' => 'text',
              'behavior' => 'title',
            ],
            'uid' => (object) [
              'label' => 'Author',
              'type' => 'integer',
              'behavior' => 'author',
            ],
            'created' => (object) [
              'label' => 'Created',
              'type' => 'integer',
              'behavior' => 'created',
            ],
            'changed' => (object) [
              'label' => 'Changed',
              'type' => 'integer',
              'behavior' => 'changed',
            ],
            'language' => (object) [
              'label' => 'Entity language',
              'type' => 'language',
              'behavior' => 'language',
            ],
            'description' => (object) [
              'label' => 'Description',
              'type' => 'text',
              'behavior' => '',
            ],
          ]),
      ],
    ];

    $tests[0]['expected_results'] = $tests[0]['database']['eck_entity_type'];
    return $tests;
  }

}
