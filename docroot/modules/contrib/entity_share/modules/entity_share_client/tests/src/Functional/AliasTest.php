<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\ImportContext;
use Drupal\node\NodeInterface;

/**
 * General functional test class for path field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class AliasTest extends EntityShareClientFunctionalTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getImportConfigProcessorSettings() {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['path_alias_processor'] = [
      'weights' => [
        'prepare_importable_entity_data' => -100,
      ],
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
            'path' => [
              'value' => [
                [
                  'alias' => '/path',
                ],
              ],
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
   * Test path alias.
   *
   * Creation, update, deletion.
   *
   * The problem is when an existing content on the client website is pulled
   * and on the server website data it is referencing an alias (with a pid) and
   * there are no more aliases with this pid on the client website.
   *
   * @see https://www.drupal.org/project/entity_share/issues/3107278#comment-14282136
   */
  public function testPath() {
    $this->pullEveryChannels();
    $this->checkCreatedEntities();
    $this->importService->getRuntimeImportContext()->clearImportedEntities();

    // Update the alias on the client site.
    $node = $this->loadEntity('node', 'es_test');
    $this->assertNotNull($node, 'The target node has been created in the first import.');

    $path = $node->get('path')->getValue();
    $this->assertEquals(2, $path[0]['pid'], 'The PID should be 2 because PID 1 is from the setup.');

    $path[0]['alias'] = '/new-alias';
    $node->set('path', $path);
    $node->save();

    $alias_storage = $this->entityTypeManager->getStorage('path_alias');
    /** @var \Drupal\path_alias\PathAliasInterface $alias */
    $alias = $alias_storage->load($path[0]['pid']);
    $this->assertEquals('/new-alias', $alias->getAlias());

    $this->pullEveryChannels();
    $this->checkCreatedEntities();
    $this->importService->getRuntimeImportContext()->clearImportedEntities();

    // Delete alias from the node, not by loading the alias entity, otherwise
    // it does not work.
    $node = $this->loadEntity('node', 'es_test');
    $this->assertNotNull($node, 'The target node already exists.');

    $path = $node->get('path')->getValue();
    $this->assertEquals(2, $path[0]['pid'], 'The PID should still be 2.');

    $path[0]['alias'] = '';
    $node->set('path', $path);
    $node->save();

    $this->pullEveryChannels();
    $this->checkCreatedEntities();
  }

  /**
   * Test path alias.
   *
   * The problem is when a content is pulled and on the server website data it
   * is referencing an alias (with a pid) and there is already an alias with
   * this pid on the client website.
   */
  public function testPathIdCollision(): void {
    // Create a third party alias.
    $alias_storage = $this->entityTypeManager->getStorage('path_alias');
    $other_alias = $alias_storage->create([
      'path' => '/',
      'alias' => '/my-other-random-alias',
      'langcode' => $this::$entityLangcode,
    ]);
    $other_alias->save();
    $this->assertEquals(2, $other_alias->id(), 'The PID should be 2 because PID 1 is from the setup.');

    // Pull manually the node, so we can change the JSON data and so make a PID
    // collision.
    $json_data = $this->getEntityJsonData('node_es_test_en', 'es_test');
    $json_data['attributes']['path']['pid'] = 2;
    $channel_id = static::$entityTypeId . '_' . static::$entityBundleId . '_' . static::$entityLangcode;
    $import_context = new ImportContext($this->remote->id(), $channel_id, $this::IMPORT_CONFIG_ID);
    $this->importService->prepareImport($import_context);
    $this->importService->importEntityListData(EntityShareUtility::prepareData($json_data));

    $node = $this->loadEntity('node', 'es_test');
    $this->assertNotNull($node, 'The target node has been created in the import.');
    $path = $node->get('path')->getValue();
    $this->assertEquals(3, $path[0]['pid'], 'The PID should be 3 because PID 2 is already used by a third party alias.');
    $this->assertEquals('/path', $path[0]['alias']);

    // Ensure the other alias had not been updated.
    $alias_storage = $this->entityTypeManager->getStorage('path_alias');
    /** @var \Drupal\path_alias\PathAliasInterface $other_alias */
    $other_alias = $alias_storage->load(2);
    $this->assertEquals('/', $other_alias->getPath());
    $this->assertEquals('/my-other-random-alias', $other_alias->getAlias());
  }

}
