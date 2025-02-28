<?php

namespace Drupal\Tests\migrate_tools\Functional;

use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;

/**
 * Execute drush on fully functional website using source generators.
 *
 * @group migrate_tools
 */
class DrushCommandsGeneratorTest extends BrowserTestBase {
  use DrushTestTrait;

  /**
   * The source CSV data.
   *
   * @var string
   */
  protected $sourceData;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'csv_source_test',
    'migrate',
    'migrate_plus',
    'migrate_source_csv',
    'migrate_tools',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup the file system so we create the source CSV.
    $this->container->get('stream_wrapper_manager')->registerWrapper('public', PublicStream::class, StreamWrapperInterface::NORMAL);
    $fs = \Drupal::service('file_system');
    $fs->mkdir('public://sites/default/files', NULL, TRUE);

    // The source data for this test.
    $this->sourceData = <<<'EOD'
vid,name,description,hierarchy,weight
tags,Tags,Use tags to group articles,0,0
forums,Sujet de discussion,Forum navigation vocabulary,1,0
test_vocabulary,Test Vocabulary,This is the vocabulary description,1,0
genre,Genre,Genre description,1,0
EOD;

    // Write the data to the filepath given in the test migration.
    file_put_contents('public://test.csv', $this->sourceData);
  }

  /**
   * Tests synced import.
   */
  public function testSyncImport(): void {
    $this->drush('mim', ['csv_source_test']);
    $this->assertStringContainsString('1/4', $this->getErrorOutput());
    $this->assertStringContainsString('4/4', $this->getErrorOutput());
    $this->assertStringContainsString('[notice] Processed 4 items (4 created, 0 updated, 0 failed, 0 ignored) - done with \'csv_source_test\'', $this->getErrorOutput());
    $this->assertStringNotContainsString('5/5', $this->getErrorOutput());
    $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load('genre');
    $this->assertEquals('Genre', $vocabulary->label());
    $this->assertEquals(4, \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->getQuery()->accessCheck(TRUE)->count()->execute());

    // Remove one vocab and replace with another.
    $this->sourceData = str_replace('genre,Genre,Genre description,1,0', 'fruit,Fruit,Fruit description,1,0', $this->sourceData);
    file_put_contents('public://test.csv', $this->sourceData);

    // Execute sync migration.
    $this->drush('mim', ['csv_source_test'], ['sync' => NULL]);
    $this->assertStringContainsString('1/4', $this->getErrorOutput());
    $this->assertStringContainsString('25% [notice] Rolled back 1 item - done with \'csv_source_test\'', $this->getErrorOutput());
    $this->assertStringContainsString('4/4', $this->getErrorOutput());
    $this->assertStringContainsString('5/5', $this->getErrorOutput());
    $this->assertStringContainsString('100% [notice] Processed 4 items (1 created, 3 updated, 0 failed, 0 ignored) - done with \'csv_source_test\'', $this->getErrorOutput());
    $this->assertEquals(4, \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->getQuery()->accessCheck(TRUE)->count()->execute());
    // Flush cache so recently deleted vocabulary actually goes away.
    drupal_flush_all_caches();
    $this->assertEmpty(\Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load('genre'));

    /** @var \Drupal\migrate\Plugin\MigrateIdMapInterface $id_map */
    $id_map = $this->container->get('plugin.manager.migration')->createInstance('csv_source_test')->getIdMap();
    $this->assertCount(4, $id_map);
  }

}
