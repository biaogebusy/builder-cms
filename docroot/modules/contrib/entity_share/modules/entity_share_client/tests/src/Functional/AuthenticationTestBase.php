<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\ImportContext;
use Drupal\node\NodeInterface;
use Drupal\Tests\key\Functional\KeyTestTrait;

/**
 * Base class for functional tests of ES authorization plugins.
 *
 * @group entity_share
 * @group entity_share_client
 */
abstract class AuthenticationTestBase extends EntityShareClientFunctionalTestBase {

  use KeyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  protected static $entityBundleId = 'es_test';

  /**
   * {@inheritdoc}
   */
  protected static $entityLangcode = 'en';

  /**
   * An array of data to generate physical files.
   *
   * @var array
   */
  protected static $filesData = [
    'private_file' => [
      'filename' => 'test_private.txt',
      'filemime' => 'text/plain',
      'uri' => 'private://test_private.txt',
      'file_content' => 'Drupal',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function getImportConfigProcessorSettings() {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['physical_file'] = [
      'rename' => FALSE,
      'weights' => [
        'process_entity' => 0,
      ],
    ];
    return $processors;
  }

  /**
   * {@inheritdoc}
   */
  protected function postSetupFixture() {
    $this->prepareContent();
    $this->populateRequestService();

    // Delete the physical file after populating the request service.
    foreach (static::$filesData as $file_data) {
      $this->fileSystem->delete($file_data['uri']);
    }

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
          'es_test_node_import_published' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
            'field_es_test_file' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'private_file'),
                  ],
                ];
              },
              'checker_callback' => 'getFilteredStructureValues',
            ],
          ]),
          'es_test_node_import_not_published' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::NOT_PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Helper function: re-imports content from JSON:API.
   *
   * @param array $channel_infos
   *   Channel infos as returned by entity_share_server entry point.
   * @param string|null $channel_id
   *   The ID of channel.
   */
  protected function reimportChannel(array $channel_infos, string $channel_id = NULL) {
    // Re-import data from JSON:API.
    // Get JSON data from the remote channel.
    if (empty($channel_id)) {
      $channel_id = static::$entityTypeId . '_' . static::$entityBundleId . '_' . static::$entityLangcode;
    }
    $channel_url = $channel_infos[$channel_id]['url'];
    $response = $this->remoteManager->jsonApiRequest($this->remote, 'GET', $channel_url);
    $json = Json::decode((string) $response->getBody());
    // Clean up the "server" content.
    $this->deleteContent();
    $this->entities = [];
    // Launch the import.
    $import_context = new ImportContext($this->remote->id(), $channel_id, $this::IMPORT_CONFIG_ID);
    $this->importService->prepareImport($import_context);
    $this->importService->importEntityListData(EntityShareUtility::prepareData($json['data']));
  }

}
