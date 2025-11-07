<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\node\NodeInterface;

/**
 * General functional test class for language not specified scenarios.
 *
 * @group entity_share
 * @group entity_share_client
 */
class LanguageNotSpecifiedTest extends EntityShareClientFunctionalTestBase {

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
  protected static $entityLangcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;

  /**
   * The locked language to test.
   *
   * @var string
   */
  protected $lockedLanguage = LanguageInterface::LANGCODE_NOT_SPECIFIED;

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
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        $this->lockedLanguage => [
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
   * Test scenarios of importing the multilingual entities.
   */
  public function testMultilingualImport() {
    // Test pulling content in the not specified language.
    $this->pullChannel('node_es_test_und');
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->loadEntity('node', 'es_test');
    $this->assertEquals($this->lockedLanguage, $node->language()->getId());

    // Reset setup.
    $this->resetImportedContent();
    $this->resetRemoteCaches();
    $this->visitedUrlsDuringSetup = [];

    // Change default language on the remote. Create manually the node in
    // not specified language on the client to emulate a change on the server.
    $prepared_translated_node = $this->entityTypeManager->getStorage('node')->create([
      'type' => static::$entityBundleId,
      'title' => $this->randomString(),
      'langcode' => 'fr',
      'uuid' => 'es_test',
    ]);
    $prepared_translated_node->save();
    $this->populateRequestService();
    $prepared_translated_node->delete();

    $client_untranslated_node = $this->entityTypeManager->getStorage('node')->create([
      'type' => static::$entityBundleId,
      'title' => $this->randomString(),
      'langcode' => $this->lockedLanguage,
      'uuid' => 'es_test',
    ]);
    $client_untranslated_node->save();

    // Test that pulling a translated content is possible and that the default
    // language had been updated.
    $this->pullChannel('node_es_test_und');
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->loadEntity('node', 'es_test');
    $this->assertEquals('fr', $node->language()->getId());
  }

}
