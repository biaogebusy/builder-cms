<?php

namespace Drupal\Tests\webform\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\webform\Controller\WebformResultsExportController;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Tests that batch export properly clears entity caches.
 *
 * @group webform
 * @coversDefaultClass \Drupal\webform\Controller\WebformResultsExportController
 */
class WebformSubmissionBatchExportTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'path',
    'path_alias',
    'field',
    'filter',
    'webform',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');
    $this->installSchema('webform', ['webform']);
    $this->installConfig('webform');
    $this->installEntitySchema('webform_submission');
    $this->installEntitySchema('user');
  }

  /**
   * Tests that entity caches are cleared after a batch export iteration.
   *
   * @covers ::batchProcess
   */
  public function testBatchExportClearsCaches(): void {
    // Create a webform with a simple text element.
    $webform = Webform::create([
      'id' => 'test_batch_export',
      'elements' => "name:\n  '#type': textfield\n  '#title': Name",
    ]);
    $webform->save();

    // Create submissions.
    $submission_ids = [];
    for ($i = 0; $i < 3; $i++) {
      $submission = WebformSubmission::create([
        'webform_id' => 'test_batch_export',
        'data' => ['name' => 'Test ' . $i],
      ]);
      $submission->save();
      $submission_ids[] = $submission->id();
    }

    // Get the entity memory cache and submission storage.
    $memory_cache = \Drupal::service('entity.memory_cache');
    $storage = \Drupal::entityTypeManager()->getStorage('webform_submission');

    // Clear caches from the setup phase so we start fresh.
    $storage->resetCache();
    $memory_cache->deleteAll();

    // Set up the exporter with access_check disabled for kernel test context.
    /** @var \Drupal\webform\WebformSubmissionExporterInterface $exporter */
    $exporter = \Drupal::service('webform_submission.exporter');
    $exporter->setWebform($webform);
    $exporter->setSourceEntity(NULL);
    $export_options = $exporter->getDefaultExportOptions();
    $export_options['access_check'] = FALSE;
    $exporter->setExporter($export_options);

    // Simulate a batch iteration by calling batchProcess directly.
    $context = [];
    WebformResultsExportController::batchProcess($webform, NULL, $export_options, $context);

    // Verify that webform submission entities are no longer in the entity
    // memory cache after the batch iteration.
    foreach ($submission_ids as $sid) {
      $cached = $memory_cache->get("values:webform_submission:$sid");
      $this->assertFalse($cached, "Submission $sid should not be in entity memory cache after batch export.");
    }

    // Verify that webform submission entities are no longer in the persistent
    // cache after the batch iteration.
    $persistent_cache = \Drupal::cache('entity');
    foreach ($submission_ids as $sid) {
      $cached = $persistent_cache->get("values:webform_submission:$sid");
      $this->assertFalse($cached, "Submission $sid should not be in persistent entity cache after batch export.");
    }

    // Verify that all submissions were actually exported.
    $this->assertEquals(3, $context['sandbox']['progress']);
    $this->assertEquals(1, $context['finished']);

    // Verify the export file was created and contains data.
    $export_file_path = $exporter->getExportFilePath();
    $this->assertFileExists($export_file_path);
    $csv_content = file_get_contents($export_file_path);
    $this->assertStringContainsString('Test 0', $csv_content);
    $this->assertStringContainsString('Test 1', $csv_content);
    $this->assertStringContainsString('Test 2', $csv_content);

    // Clean up the export file.
    @unlink($export_file_path);
  }

}
