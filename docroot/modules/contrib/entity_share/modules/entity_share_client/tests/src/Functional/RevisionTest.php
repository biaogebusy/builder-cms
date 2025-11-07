<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Component\Utility\UrlHelper;
use Drupal\entity_share_client\ImportContext;
use Drupal\node\NodeInterface;

/**
 * Functional test class to test import plugin "Revision".
 *
 * @group entity_share
 * @group entity_share_client
 */
class RevisionTest extends EntityShareClientFunctionalTestBase {

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
   * Array to store JSON:API URLs for different "passes".
   *
   * @var array
   */
  protected $urlsByPass = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Prepare content in the initial state.
    $this->prepareContent();
    $this->populateRequestService();

    // Modify the content and populate the JSON:API request mapping, but
    // slightly alter the URL so that we have both states cached. The second
    // URL will be fetched when we need to pull the "modified" entities.
    $this->editContentAndRepopulate(2);

    // Now we can delete the "remote" entities.
    $this->deleteContent();
  }

  /**
   * Update all entities by changing the labels, and populate response mapping.
   *
   * @param int $version
   *   The ordinal number of content modification.
   */
  protected function editContentAndRepopulate(int $version) {
    foreach ($this->entities as $entities_per_type) {
      foreach ($entities_per_type as $entity) {
        $entity->set('title', $entity->label() . " V$version")->save();
      }
    }
    foreach ($this->visitedUrlsDuringSetup as $url) {
      $parsed_url = UrlHelper::parse($url);
      // Just add the fake parameter (ie. `&2=2`) which doesn't affect JSON:API
      // response, but is different from the regular JSON:API URL.
      $parsed_url['query'][$version] = $version;
      $query = UrlHelper::buildQuery($parsed_url['query']);
      $prepared_url = $parsed_url['path'] . '?' . $query;
      // Issue the request in order to populate response mapping with different
      // data.
      $this->remoteManager->request($this->remote, 'GET', $prepared_url);
      // For convenience, save the N'th version of the URL in a class variable.
      $this->urlsByPass[$url][$version] = $prepared_url;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getImportConfigProcessorSettings() {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['revision'] = [
      'weights' => [
        'process_entity' => 10,
      ],
      'enforce_new_revision' => TRUE,
      'translation_affected' => FALSE,
    ];
    return $processors;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          'es_test' => $this->getCompleteNodeInfos([
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
   * Test the "Revision" Import Processor plugin.
   *
   * Test in enabled and disabled state.
   */
  public function testRevisionPlugin() {
    // Verify that entities can be pulled with this plugin enabled.
    $this->pullEveryChannels();
    $this->checkCreatedEntities();

    // Verify that pulled entity has just one revision.
    $imported_node = $this->loadEntity('node', 'es_test');
    $revision_ids = $this->entityTypeManager->getStorage('node')->revisionIds($imported_node);
    $this->assertEquals(1, count($revision_ids), "After the initial import, node " . $imported_node->uuid() . " has only one revision.");

    $this->importService->getRuntimeImportContext()->clearImportedEntities();

    // Prepare import context.
    $channel_id = static::$entityTypeId . '_' . static::$entityBundleId . '_' . static::$entityLangcode;
    $import_context = new ImportContext($this->remote->id(), $channel_id, $this::IMPORT_CONFIG_ID);

    // Import data from the remote URL: here we are using the 2nd version
    // of the channel URL, which contains data after the modification.
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);
    $channel_url = $channel_infos[$channel_id]['url'];
    $current_pass_url = $this->urlsByPass[$channel_url][2];

    $this->importService->prepareImport($import_context);
    $this->importService->importFromUrl($current_pass_url);

    // Verify that pulled entity has two revisions.
    $imported_node = $this->loadEntity('node', 'es_test');
    $revision_ids = $this->entityTypeManager->getStorage('node')->revisionIds($imported_node);
    $this->assertEquals(2, count($revision_ids), "After the second import, node " . $imported_node->uuid() . " has two revisions.");

    // Disable the import plugin.
    $this->removePluginFromImportConfig('revision');
    // Reset all content.
    $this->resetImportedContent();
    $this->urlsByPass = [];

    // Prepare content twice.
    $this->prepareContent();
    $this->populateRequestService();
    $this->editContentAndRepopulate(2);
    $this->deleteContent();

    // Pull twice and the number of revisions should always be one.
    $this->pullEveryChannels();
    $imported_node = $this->loadEntity('node', 'es_test');
    $revision_ids = $this->entityTypeManager->getStorage('node')->revisionIds($imported_node);
    $this->assertEquals(1, count($revision_ids), "After the initial import, node " . $imported_node->uuid() . " has only one revision.");

    $import_context = new ImportContext($this->remote->id(), $channel_id, $this::IMPORT_CONFIG_ID);
    $channel_infos = $this->remoteManager->getChannelsInfos($this->remote);
    $channel_url = $channel_infos[$channel_id]['url'];
    $current_pass_url = $this->urlsByPass[$channel_url][2];
    $this->importService->prepareImport($import_context);
    $this->importService->importFromUrl($current_pass_url);

    $imported_node = $this->loadEntity('node', 'es_test');
    $revision_ids = $this->entityTypeManager->getStorage('node')->revisionIds($imported_node);
    $this->assertEquals(1, count($revision_ids), "After the second import, node " . $imported_node->uuid() . " has only one revision.");
  }

}
