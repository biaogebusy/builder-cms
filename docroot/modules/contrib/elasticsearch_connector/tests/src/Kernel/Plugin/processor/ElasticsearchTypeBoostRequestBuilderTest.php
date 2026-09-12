<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Kernel\Plugin\processor;

use Drupal\elasticsearch_connector\Plugin\search_api\processor\ElasticsearchTypeBoost;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\Query;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests how the Elasticsearch Type Boost processor handles responses.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\Plugin\search_api\processor\ElasticsearchTypeBoost
 *
 * @group elasticsearch_connector
 */
class ElasticsearchTypeBoostRequestBuilderTest extends KernelTestBase {
  use ProphecyTrait;

  /**
   * Data provider for testPreprocessTypeBoostSearchQuery().
   *
   * @return array<string, array{array, ?array}>
   *   An array, containing:
   *   - an array of Elasticsearch Type Boost Search API Processor
   *     configuration;
   *   - an array of type-boosting clause we expect from the configuration, or
   *     NULL if we don't expect a type-boosting clause to be generated.
   */
  public static function preprocessSearchQueryDataProvider(): array {
    return [
      'No type boost 1' => [[], NULL],
      'No type boost 2' => [['boosts' => []], NULL],
      'No type boost 3' => [['boosts' => ['node' => ['bundle_boosts' => []]]], NULL],
      'No type boost 4' => [['boosts' => ['node' => ['datasource_boost' => []]]], NULL],
      'No type boost 5' => [
        // Input configuration.
        [
          'boosts' => [
            'node' => [
              'bundle_boosts' => NULL,
              'datasource_boost' => NULL,
            ],
          ],
        ],
        // Expected output.
        NULL,
      ],
      'One datasource boost' => [
        // Input configuration.
        ['boosts' => ['node' => ['datasource_boost' => '1.10']]],
        // Expected query fragment (output).
        [
          [
            'filter' => ['match' => ['search_api_datasource' => 'node']],
            'weight' => 1.1,
          ],
        ],
      ],
      'Two datasource boosts' => [
        // Input configuration.
        [
          'boosts' => [
            'node' => ['datasource_boost' => '1.20'],
            'block' => ['datasource_boost' => '2.10'],
          ],
        ],
        // Expected query fragment (output).
        [
          [
            'filter' => ['match' => ['search_api_datasource' => 'node']],
            'weight' => 1.2,
          ],
          [
            'filter' => ['match' => ['search_api_datasource' => 'block']],
            'weight' => 2.1,
          ],
        ],
      ],
      'One bundle boost' => [
        // Input configuration.
        [
          'boosts' => [
            'node' => [
              'bundle_boosts' => [
                'article' => '3.10',
              ],
            ],
          ],
        ],
        // Expected query fragment (output).
        [
          [
            'filter' => ['match' => ['type' => 'article']],
            'weight' => 3.1,
          ],
        ],
      ],
      'Two bundle boosts' => [
        // Input configuration.
        [
          'boosts' => [
            'node' => [
              'bundle_boosts' => [
                'article' => '4.10',
                'page' => '4.20',
              ],
            ],
          ],
        ],
        // Expected query fragment (output).
        [
          [
            'filter' => ['match' => ['type' => 'article']],
            'weight' => 4.1,
          ],
          [
            'filter' => ['match' => ['type' => 'page']],
            'weight' => 4.2,
          ],
        ],
      ],
      'Both datasource and type boosts' => [
        // Input configuration.
        [
          'boosts' => [
            'node' => [
              'datasource_boost' => '5.20',
              'bundle_boosts' => [
                'article' => '5.10',
                'page' => NULL,
              ],
            ],
          ],
        ],
        // Expected query fragment (output).
        [
          [
            'filter' => ['match' => ['type' => 'article']],
            'weight' => 5.1,
          ],
          [
            'filter' => ['match' => ['type' => 'page']],
            'weight' => 5.2,
          ],
        ],
      ],
    ];
  }

  /**
   * Test we can build a function_score query fragment from processor config.
   *
   * @param array $processorConfig
   *   An array of Elasticsearch Type Boost Search API Processor configuration.
   * @param ?array $expectedFragment
   *   The expected type-boosting clause we expect from the configuration, or
   *   NULL if we don't expect a type-boosting clause to be generated.
   *
   * @dataProvider preprocessSearchQueryDataProvider
   */
  public function testPreprocessTypeBoostSearchQuery(array $processorConfig, ?array $expectedFragment): void {
    // Setup: Instantiate an ElasticsearchTypeBoost plugin with the
    // configuration we are using for this test case.
    $processor = new ElasticsearchTypeBoost($processorConfig, 'elasticsearch_type_boost', []);

    // Setup: Create a mock index.
    $index = $this->prophesize(IndexInterface::class);
    $index->status()->willReturn(TRUE);

    // Setup: Create a query.
    $query = Query::create($index->reveal(), []);

    // SUT: Preprocess the query using the system under test.
    $processor->preprocessSearchQuery($query);

    // Assert: The query fragment should match our expectations.
    $actualFragment = $query->getOption('elasticsearch_connector_type_boost_functions');
    $this->assertEquals($expectedFragment, $actualFragment);
  }

}
