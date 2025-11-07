<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\node\NodeInterface;

/**
 * Test class for file field where the physical file had been deleted.
 *
 * @group entity_share
 * @group entity_share_client
 */
class FileDeletedTest extends FileTest {

  /**
   * {@inheritdoc}
   */
  protected static $filesData = [
    'public_file_deleted' => [
      'filename' => 'test_deleted.txt',
      'filemime' => 'text/plain',
      'uri' => 'public://test_deleted.txt',
      'file_content' => 'Drupal',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function postSetupFixture() {
    $this->prepareContent();

    // Delete the physical file before populating the request service.
    foreach (static::$filesData as $file_data) {
      $this->fileSystem->delete($file_data['uri']);
    }

    $this->populateRequestService();
    $this->deleteContent();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'file' => [
        'en' => $this->preparePhysicalFilesAndFileEntitiesData(),
      ],
      'node' => [
        'en' => [
          // Basic public file.
          'es_test_public_file' => $this->getCompleteNodeInfos([
            'field_es_test_file' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'public_file_deleted'),
                  ],
                ];
              },
              'checker_callback' => 'getFilteredStructureValues',
            ],
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test basic pull feature.
   */
  public function testBasicPull() {
    foreach (static::$filesData as $file_data) {
      $this->assertFalse(file_exists($file_data['uri']), 'The physical file ' . $file_data['filename'] . ' has been deleted.');
    }

    $this->pullEveryChannels();
    $this->checkCreatedEntities();

    foreach (static::$filesData as $file_data) {
      $this->assertFalse(file_exists($file_data['uri']), 'The physical file ' . $file_data['filename'] . ' has not been recreated.');
    }
  }

}
