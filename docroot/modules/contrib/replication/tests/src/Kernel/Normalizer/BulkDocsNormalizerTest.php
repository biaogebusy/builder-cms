<?php

namespace Drupal\Tests\replication\Kernel\Normalizer;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\entity_test\Entity\EntityTestMulRev;
use Drupal\replication\BulkDocs\BulkDocsInterface;

/**
 * Tests the content serialization format.
 *
 * @group replication
 */
class BulkDocsNormalizerTest extends NormalizerTestBase {

  protected $entityClass = 'Drupal\entity_test\Entity\EntityTest';

  /**
   * Array with test entities.
   */
  protected $testEntities = [];

  /**
   * @var \Drupal\replication\BulkDocs\BulkDocsInterface
   */
  protected $bulkDocs;

  /**
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Array with test values for test entities.
   */
  protected $testValues = [];

  /**
   * Number of test values to generate.
   */
  protected $testValuesNumber = 3;

  protected function setUp() {
    parent::setUp();
    $this->testEntities = $this->createTestEntities('entity_test_mulrev', $this->testValuesNumber);

    $this->workspaceManager = $this->container->get('workspace.manager');

    $this->bulkDocs = $this->container
      ->get('replication.bulkdocs_factory')
      ->get($this->workspaceManager->getActiveWorkspace());

    $this->bulkDocs->setEntities($this->testEntities);
    $this->bulkDocs->save();
  }

  public function testNormalizer() {
    // Test normalize.
    $expected = [];
    for ($key = 0; $key < $this->testValuesNumber; $key++) {
      $entity = EntityTestMulRev::load($key + 1);
      $expected[$key] = [
        'ok' => TRUE,
        'id' => $entity->uuid(),
        'rev' => $entity->_rev->value,
      ];
    }

    $normalized = $this->serializer->normalize($this->bulkDocs);

    $entity_number = 1;
    foreach ($expected as $key => $value) {
      foreach (array_keys($value) as $value_key) {
        $this->assertEquals($value[$value_key], $normalized[$key][$value_key], "Field $value_key is normalized correctly for entity number $entity_number.");
      }
      $this->assertEquals(array_diff_key($normalized[$key], $expected[$key]), [], 'No unexpected data is added to the normalized array.');
      $entity_number++;
    }

    // Test serialize.
    $expected = json_encode($normalized);
    // Paranoid test because JSON serialization is tested elsewhere.
    $actual = $this->serializer->serialize($this->bulkDocs, 'json');
    $this->assertSame($actual, $expected, 'Entity serializes correctly to JSON.');

    // Test denormalize.
    $data = ['docs' => []];
    foreach ($this->testEntities as $entity) {
      $data['docs'][] = $this->serializer->normalize($entity);
    }
    $context = ['workspace' => $this->workspaceManager->getActiveWorkspace()];
    $bulk_docs = $this->serializer->denormalize($data, 'Drupal\replication\BulkDocs\BulkDocs', 'json', $context);
    $this->assertTrue($bulk_docs instanceof BulkDocsInterface, 'Denormalized data is an instance of the correct interface.');
    foreach ($bulk_docs->getEntities() as $key => $entity) {
      $entity_number = $key+1;
      $this->assertTrue($entity instanceof $this->entityClass, new FormattableMarkup("Denormalized entity number $entity_number is an instance of @class", ['@class' => $this->entityClass]));
      $this->assertSame($entity->getEntityTypeId(), $this->testEntities[$key]->getEntityTypeId(), "Expected entity type foundfor entity number $entity_number.");
      $this->assertSame($entity->bundle(), $this->testEntities[$key]->bundle(), "Expected entity bundle found for entity number $entity_number.");
      $this->assertSame($entity->uuid(), $this->testEntities[$key]->uuid(), "Expected entity UUID found for entity number $entity_number.");
    }

    // @todo {@link https://www.drupal.org/node/2600460 Test context switches.}
  }

  protected function createTestEntities($entity_type, $number = 3) {
    $entities = [];
    $entity_manager = \Drupal::entityManager();

    while ($number >= 1) {
      $values = [
        'name' => $this->randomMachineName(),
        'user_id' => 0,
        'field_test_text' => [
          'value' => $this->randomMachineName(),
          'format' => 'full_html',
        ],
      ];
      $this->testValues[] = $values;
      $entity = $entity_manager->getStorage($entity_type)->create($values);
      $entity->save();
      $entities[] = $entity;
      $number--;
    }

    return $entities;
  }

}
