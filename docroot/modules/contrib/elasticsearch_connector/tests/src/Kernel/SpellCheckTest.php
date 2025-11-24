<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;

// cspell:ignore tost


/**
 * Tests spellcheck functionality.
 *
 * @group elasticsearch_connector
 */
class SpellCheckTest extends KernelTestBase {

  use ElasticSearchTestTrait;
  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'search_api',
    'elasticsearch_connector',
    'elasticsearch_connector_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected $serverId = 'elasticsearch_server';

  /**
   * {@inheritdoc}
   */
  protected $indexId = 'test_elasticsearch_index';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');

    $this->installConfig([
      'search_api',
      'elasticsearch_connector',
      'elasticsearch_connector_test',
    ]);

    $this->setUpExampleStructure();
    $this->insertExampleContent();
    $this->indexItems($this->indexId);
    $this->refreshIndices();
  }

  /**
   * Tests spellcheck functionality.
   */
  public function testSpellCheck() {

    // search_api's example content contains the string 'test' repeatedly.
    // Check if a search for 'tost' brings back 'test' as a suggestion.
    $keys = ['tost'];

    $query = $this->getIndex()->query();
    $query->keys($keys);
    $query->setOption('search_api_spellcheck', [
      'keys' => $keys,
      'count' => 1,
    ]);
    $results = $query->execute();

    $expected = [
      'suggestions' => [
        'tost' => [
          'test',
        ],
      ],
    ];

    $spellcheck = $results->getExtraData('search_api_spellcheck');
    $this->assertEquals($expected, $spellcheck, 'Spellcheck returned expected result.');
  }

}
