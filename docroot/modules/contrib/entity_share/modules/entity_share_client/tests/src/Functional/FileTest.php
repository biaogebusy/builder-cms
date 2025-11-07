<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\node\NodeInterface;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Functional test class for file field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class FileTest extends EntityShareClientFunctionalTestBase {
  use TestFileCreationTrait;

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
    'public_file' => [
      'filename' => 'test.txt',
      'filemime' => 'text/plain',
      'uri' => 'public://test.txt',
      'file_content' => 'Drupal',
    ],
    'public_file_sub_directory' => [
      'filename' => 'test_sub_directory.txt',
      'filemime' => 'text/plain',
      'uri' => 'public://sub-directory/test_sub_directory.txt',
      'file_content_callback' => 'generateBigFile',
    ],
    'private_file_sub_directory' => [
      'filename' => 'test_private.txt',
      'filemime' => 'text/plain',
      'uri' => 'private://sub-directory/test_private.txt',
      'file_content' => 'Drupal',
    ],
    'public_jpg' => [
      'filename' => 'image-test.jpg',
      'filemime' => 'image/jpeg',
      'uri' => 'public://image-test.jpg',
    ],
    'public_png' => [
      'filename' => 'image-test.png',
      'filemime' => 'image/png',
      'uri' => 'public://image-test.png',
    ],
  ];

  /**
   * An array of file size keyed by file UUID.
   *
   * @var array
   */
  protected $filesSize = [];

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   * Bug in PHPMD, @see https://github.com/phpmd/phpmd/issues/714
   */
  protected function setUp(): void {
    parent::setUp();

    $this->getTestFiles('image');
    // Special case for the images created using native helper method.
    if (isset(static::$filesData['public_jpg'])) {
      $this->filesSize['public_jpg'] = filesize(static::$filesData['public_jpg']['uri']);
    }
    if (isset(static::$filesData['public_png'])) {
      $this->filesSize['public_png'] = filesize(static::$filesData['public_png']['uri']);
    }

    $this->postSetupFixture();
  }

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
          // Basic public file.
          'es_test_public_file' => $this->getCompleteNodeInfos([
            'field_es_test_file' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'public_file'),
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
          // Public file with hidden description.
          'es_test_public_file_description_hidden' => $this->getCompleteNodeInfos([
            'field_es_test_file' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'public_file'),
                    'display' => 0,
                    // Can't use faker because it is a value_callback to handle
                    // the target_id.
                    'description' => 'Description 1',
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
          // Public file with shown description.
          'es_test_public_file_description_shown' => $this->getCompleteNodeInfos([
            'field_es_test_file' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'public_file'),
                    'display' => 1,
                    // Can't use faker because it is a value_callback to handle
                    // the target_id.
                    'description' => 'Description 2',
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
          // Public file in sub-directory.
          'es_test_public_file_sub_directory' => $this->getCompleteNodeInfos([
            'field_es_test_file' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'public_file_sub_directory'),
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
          // Private file in sub-directory.
          'es_test_private_file_sub_directory' => $this->getCompleteNodeInfos([
            'field_es_test_file' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'private_file_sub_directory'),
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
          // PNG.
          'es_test_png' => $this->getCompleteNodeInfos([
            'field_es_test_image' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'public_png'),
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
          // JPEG + alt text.
          'es_test_jpeg_alt' => $this->getCompleteNodeInfos([
            'field_es_test_image' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'public_jpg'),
                    // Can't use faker because it is a value_callback to handle
                    // the target_id.
                    'alt' => 'Alt text 1',
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
          // JPEG + title text.
          'es_test_jpeg_title' => $this->getCompleteNodeInfos([
            'field_es_test_image' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'public_jpg'),
                    // Can't use faker because it is a value_callback to handle
                    // the target_id.
                    'title' => 'Title text 1',
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
          // JPEG + alt text + title text.
          'es_test_jpeg_alt_title' => $this->getCompleteNodeInfos([
            'field_es_test_image' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'public_jpg'),
                    // Can't use faker because it is a value_callback to handle
                    // the target_id.
                    'alt' => 'Alt text 2',
                    'title' => 'Title text 2',
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
    $this->commonBasicPull();

    // Test again without the import plugin "Physical file".
    $this->removePluginFromImportConfig('physical_file');
    // Need to remove all imported content (and files) prior to that.
    $this->resetImportedContent();

    foreach (static::$filesData as $file_data) {
      $this->assertFalse(file_exists($file_data['uri']), 'The physical file ' . $file_data['filename'] . ' has been deleted.');
    }

    // Pull just one entity with attached file, and without this plugin
    // the file should not exist.
    $this->pullEveryChannels();
    $this->checkCreatedEntities();

    foreach (static::$filesData as $file_data) {
      $this->assertFalse(file_exists($file_data['uri']), 'The physical file ' . $file_data['filename'] . ' has not been pulled and recreated.');
    }

    // Test rename option.
    // Re-enable the plugin.
    $this->mergePluginsToImportConfig([
      'physical_file' => [
        'rename' => FALSE,
        'weights' => [
          'process_entity' => 0,
        ],
      ],
    ]);
    // Need to remove all imported content (and files) prior to that.
    $this->resetImportedContent();
    // Pull twice to test that by default there are no duplicated physical
    // files.
    $this->pullEveryChannels();
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $this->pullEveryChannels();

    // Test that the files had been recreated without rename.
    foreach (static::$filesData as $file_data) {
      $this->assertTrue(file_exists($file_data['uri']), 'The physical file ' . $file_data['filename'] . ' has been pulled and recreated.');
      $replaced_file_info = $this->getReplacedFileInfo($file_data);
      $this->assertFalse(file_exists($replaced_file_info['uri']), 'The physical file ' . $replaced_file_info['filename'] . ' has not been renamed.');
    }

    // Need to remove all imported content (and files) prior to that.
    $this->resetImportedContent();

    // Update the 'rename' setting.
    $this->mergePluginsToImportConfig([
      'physical_file' => [
        'rename' => TRUE,
      ],
    ]);

    $this->pullEveryChannels();
    $this->importService->getRuntimeImportContext()->clearImportedEntities();

    // At the first import there should not be duplicated files.
    foreach (static::$filesData as $file_data) {
      $this->assertTrue(file_exists($file_data['uri']), 'The physical file ' . $file_data['filename'] . ' has been pulled and recreated.');
      $replaced_file_info = $this->getReplacedFileInfo($file_data);
      $this->assertFalse(file_exists($replaced_file_info['uri']), 'The physical file ' . $replaced_file_info['filename'] . ' has not been renamed.');
    }

    $this->pullEveryChannels();

    // At the second import there should be duplicated files.
    foreach (static::$filesData as $file_data) {
      $this->assertTrue(file_exists($file_data['uri']), 'The physical file ' . $file_data['filename'] . ' still exists.');
      $replaced_file_info = $this->getReplacedFileInfo($file_data);
      $this->assertTrue(file_exists($replaced_file_info['uri']), 'The physical file ' . $replaced_file_info['filename'] . ' has been created.');
    }
  }

  /**
   * Helper function.
   *
   * @param string $file_uuid
   *   The file UUID.
   * @param array $file_data
   *   The file data as in static::filesData.
   */
  protected function generateBigFile($file_uuid, array $file_data) {
    // 100 MB.
    $size = 100000000;
    $file_pointer = fopen($file_data['uri'], 'w');
    fseek($file_pointer, $size - 1, SEEK_CUR);
    fwrite($file_pointer, 'a');
    fclose($file_pointer);
    $this->filesSize[$file_uuid] = filesize($file_data['uri']);
  }

  /**
   * Get the replaced file infos.
   *
   * @param array $file_data
   *   The file's data with the same structure as in static::$filesData.
   *
   * @return array
   *   The array of data for the replaced file. Same structure as input.
   */
  protected function getReplacedFileInfo(array $file_data) {
    $replaced_file_data = $file_data;

    $uri = $file_data['uri'];
    $filename = $file_data['filename'];

    // Generate replaced file name.
    $parts = pathinfo($filename);
    $replaced_file_name = $parts['filename'] . '_0.' . $parts['extension'];
    $replaced_file_data['filename'] = $replaced_file_name;

    // Generate replaced URI.
    $replaced_file_data['uri'] = str_replace($filename, $replaced_file_name, $uri);
    return $replaced_file_data;
  }

}
