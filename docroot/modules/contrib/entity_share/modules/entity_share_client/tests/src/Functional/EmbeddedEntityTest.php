<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Functional test class for embedded entities in RTE fields.
 *
 * @group entity_share
 * @group entity_share_client
 */
class EmbeddedEntityTest extends EntityShareClientFunctionalTestBase {
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
    'file_document' => [
      'filename' => 'sample.pdf',
      'filemime' => 'application/pdf',
      'uri' => 'public://sample.pdf',
      'file_content_callback' => 'getMediaEntityReferenceTestFiles',
    ],
    'file_image' => [
      'filename' => 'image-test.jpg',
      'filemime' => 'image/jpeg',
      'uri' => 'public://image-test.jpg',
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
    // Special case for the image created using native helper method.
    if (isset(static::$filesData['file_image'])) {
      $this->filesSize['file_image'] = filesize(static::$filesData['file_image']['uri']);
    }

    $this->entityTypeManager->getStorage('jsonapi_resource_config')->create([
      'id' => 'node--es_test',
      'disabled' => FALSE,
      'path' => 'node/es_test',
      'resourceType' => 'node--es_test',
      'resourceFields' => [
        'field_es_test_text_formatted_lon' => [
          'fieldName' => 'field_es_test_text_formatted_lon',
          'publicName' => 'field_es_test_text_formatted_lon',
          'enhancer' => [
            'id' => 'entity_share_embedded_entities',
          ],
          'disabled' => FALSE,
        ],
      ],
    ])->save();

    $this->postSetupFixture();
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
  protected function getImportConfigProcessorSettings() {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['physical_file'] = [
      'rename' => FALSE,
      'weights' => [
        'process_entity' => 0,
      ],
    ];
    $processors['embedded_entity_importer'] = [
      'max_recursion_depth' => -1,
      'weights' => [
        'prepare_importable_entity_data' => 20,
      ],
    ];

    return $processors;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'file' => [
        'en' => $this->preparePhysicalFilesAndFileEntitiesData(),
      ],
      'media' => [
        'en' => [
          'es_test_document' => $this->getCompleteMediaInfos([
            'field_es_test_document' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('file', 'file_document'),
                    'display' => 1,
                  ],
                ];
              },
              'checker_callback' => 'getFilteredStructureValues',
            ],
            'bundle' => [
              'value' => 'es_test_document',
              'checker_callback' => 'getTargetId',
            ],
          ]),
        ],
      ],
      'node' => [
        'en' => [
          'es_test_embedding' => $this->getCompleteNodeInfos([
            'field_es_test_text_formatted_lon' => [
              'value_callback' => [$this, 'getEmbeddedTextValue'],
              'checker_callback' => 'getValues',
            ],
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
        'fr' => [
          'es_test_embedded_linkit' => $this->getCompleteNodeInfos([]),
          'es_test_embedded_entity_embed' => $this->getCompleteNodeInfos([]),
        ],
      ],
    ];
  }

  /**
   * Test basic pull feature.
   */
  public function testBasicPull() {
    // As only the default channel is defined. Only es_test_embedding will be
    // pulled but the other entities will be pulled because of embedded plugins.
    $this->commonBasicPull();
  }

  /**
   * Helper function to generate RTE content.
   *
   * @return string[][]
   *   The RTE field value.
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   * Bug in PHPMD, @see https://github.com/phpmd/phpmd/issues/714
   */
  protected function getEmbeddedTextValue() {
    $image_src = $this->fileUrlGenerator->generateString(static::$filesData['file_image']['uri']);

    $value = <<<EOT
<p>Test image</p>

<img alt="alt" data-align="center" data-entity-type="file" data-entity-uuid="file_image" src="$image_src" />

<p>Test Linkit</p>

<p><a data-entity-substitution="canonical" data-entity-type="node" data-entity-uuid="es_test_embedded_linkit" href="/node/666">Test Linkit</a></p>

<p>Test Entity Embed</p>

<drupal-entity data-align="right" data-caption="test" data-embed-button="node" data-entity-embed-display="view_mode:node.teaser" data-entity-type="node" data-entity-uuid="es_test_embedded_entity_embed" data-langcode="fr"></drupal-entity>

<p>Test Media core</p>

<drupal-media data-align="center" data-entity-type="media" data-entity-uuid="es_test_document"></drupal-media>
EOT;

    return [
      [
        'value' => $value,
        'format' => 'full_html',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();

    // Needs to make the requests when only the embedded content will be
    // required.
    // Nodes.
    $route_name = sprintf('jsonapi.%s--%s.individual', 'node', 'es_test');
    $linked_content_url = Url::fromRoute($route_name, [
      'entity' => 'es_test_embedded_linkit',
    ])
      ->setOption('language', $this->container->get('language_manager')->getLanguage('en'))
      ->setOption('absolute', TRUE);
    $this->discoverJsonApiEndpoints($linked_content_url->toString());
    $linked_content_url = Url::fromRoute($route_name, [
      'entity' => 'es_test_embedded_entity_embed',
    ])
      ->setOption('language', $this->container->get('language_manager')->getLanguage('en'))
      ->setOption('absolute', TRUE);
    $this->discoverJsonApiEndpoints($linked_content_url->toString());

    // File.
    // File document will be detected with the media.
    $route_name = sprintf('jsonapi.%s--%s.individual', 'file', 'file');
    $linked_content_url = Url::fromRoute($route_name, [
      'entity' => 'file_image',
    ])
      ->setOption('language', $this->container->get('language_manager')->getLanguage('en'))
      ->setOption('absolute', TRUE);
    $this->discoverJsonApiEndpoints($linked_content_url->toString());

    // Media.
    $route_name = sprintf('jsonapi.%s--%s.individual', 'media', 'es_test_document');
    $linked_content_url = Url::fromRoute($route_name, [
      'entity' => 'es_test_document',
    ])
      ->setOption('language', $this->container->get('language_manager')->getLanguage('en'))
      ->setOption('absolute', TRUE);
    $this->discoverJsonApiEndpoints($linked_content_url->toString());
  }

}
