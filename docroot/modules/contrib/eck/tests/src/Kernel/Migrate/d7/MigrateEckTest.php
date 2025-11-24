<?php

namespace Drupal\Tests\eck\Kernel\Migrate\d7;

use Drupal\Tests\file\Kernel\Migrate\d7\FileMigrationSetupTrait;

/**
 * Tests migration of ECK entities.
 *
 * @group eck
 */
class MigrateEckTest extends MigrateEckTestBase {

  use FileMigrationSetupTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'content_translation',
    'eck',
    'language',
    'node',
    'text',
    'file',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installConfig(static::$modules);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->fileMigrationSetup();
    $this->executeMigrations([
      'language',
      'default_language',
      'd7_eck_type',
      'd7_eck_bundle',
      'd7_comment_type',
    ]);
    $this->migrateFields();
    $this->executeMigrations([
      'd7_eck',
      'd7_eck_translation',
    ]);
  }

  /**
   * Tests migrating Eck entity types.
   */
  public function testEck() {
    $results = [
      [
        'type' => 'simple_entity',
        'bundle' => 'simple_entity',
        'id' => 1,
        'label' => 'Simple entity 1',
        'langcode' => 'und',
        'fields' => [
          'field_text' => [0 => ['value' => 'Simple entity 1 text value.']],
        ],
      ],
      [
        'type' => 'complex_entity',
        'bundle' => 'complex_entity',
        'id' => '1',
        'label' => 'Complex entity 1',
        'langcode' => 'en',
        'fields' => [
          'field_text' => [
            0 => ['value' => 'Complex entity text value - English version.'],
          ],
          'field_complex_entity' => [0 => ['target_id' => '3']],
          'field_file' => [
            0 => [
              'target_id' => '1',
              'display' => '1',
              'description' => '',
            ],
          ],
          'field_node' => [0 => ['target_id' => '1']],
          'field_simple_entities' => [
            0 => ['target_id' => '1'],
            1 => ['target_id' => '2'],
          ],
        ],
        'translations' => [
          'fr' => [
            'fields' => [
              'field_text' => [
                0 => ['value' => 'Complex entity text value - French version.'],
              ],
            ],
          ],
        ],
      ],
      [
        'type' => 'complex_entity',
        'bundle' => 'complex_entity',
        'id' => '3',
        'label' => 'Complex entity 3',
        'langcode' => 'fr',
        'fields' => [
          'field_text' => [
            0 => ['value' => 'Complex entity 3 text value - French version.'],
          ],
        ],
      ],
      [
        'type' => 'complex_entity',
        'bundle' => 'another_bundle',
        'id' => '4',
        'label' => 'Entity of another complex bundle 1',
        'langcode' => 'en',
        'fields' => [
          'field_text' => [
            0 => ['value' => 'Text value of another complex bundle 1.'],
          ],
        ],
      ],
    ];

    $count = \Drupal::entityQuery('simple_entity')->count()->accessCheck(FALSE)->execute();
    $this->assertSame(2, $count);

    $count = \Drupal::entityQuery('complex_entity')->count()->accessCheck(FALSE)->execute();
    $this->assertSame(5, $count);

    foreach ($results as $result) {
      $this->assertEck($result);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getFileMigrationInfo() {
    return [
      'path' => 'public://sites/default/files/test.txt',
      'size' => '14',
      'base_path' => 'public://',
      'plugin_id' => 'd7_file',
    ];
  }

}
