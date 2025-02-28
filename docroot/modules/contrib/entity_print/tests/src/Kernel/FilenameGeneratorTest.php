<?php

namespace Drupal\Tests\entity_print\Kernel;

use Drupal\entity_print\FilenameGeneratorInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * @coversDefaultClass \Drupal\entity_print\FilenameGenerator
 * @group entity_print
 */
class FilenameGeneratorTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * An array of modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'filter',
    'entity_print',
  ];

  /**
   * The filename generator.
   *
   * @var \Drupal\entity_print\FilenameGeneratorInterface
   */
  protected $filenameGenerator;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'filter']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->filenameGenerator = $this->container->get('entity_print.filename_generator');
  }

  /**
   * Test filename generation for the content entities.
   *
   * @covers ::generateFilename
   * @dataProvider generateFilenameDataProvider
   */
  public function testGenerateFilename($title, $expected_filename) {
    $node = $this->createNode(['title' => $title]);
    $this->assertEquals($expected_filename, $this->filenameGenerator->generateFilename([$node]));
  }

  /**
   * Get the data for testing filename generation.
   *
   * @return array
   *   An array of data rows for testing filename generation.
   */
  public function generateFilenameDataProvider() {
    return [
      // $node_title, $expected_filename.
      ['Random Node Title', 'Random Node Title'],
      ['Title -=with special chars&*#', 'Title with special chars'],
      ['Title 5 with Nums 2', 'Title 5 with Nums 2'],
      ['Dußeldorf will be transliterated', 'Dusseldorf will be transliterated'],
      // Ensure invalid filenames get the default.
      [' ', FilenameGeneratorInterface::DEFAULT_FILENAME],
    ];
  }

  /**
   * Test the filename when using multiple entities.
   */
  public function testFilenameMultipleEntities() {
    $entities = [
      $this->createNode(['title' => 'entity1']),
      $this->createNode(['title' => 'entity2']),
    ];
    $this->assertEquals('entity1-entity2', $this->filenameGenerator->generateFilename($entities));
  }

  /**
   * Test filename generation with a custom label callback.
   */
  public function testGenerateCustomCallback() {
    $node = $this->createNode([]);
    $this->assertEquals($node->label() . 'appended', $this->filenameGenerator->generateFilename([$node], function ($entity) {
      return $entity->label() . 'appended';
    }));
  }

}
