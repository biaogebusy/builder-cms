<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI\Query;

use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\elasticsearch_connector\SearchAPI\Query\SearchParamBuilder;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\ParseMode\ParseModeInterface;
use Drupal\search_api\Query\QueryInterface;
use MakinaCorpus\Lucene\Query;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the search param builder.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\SearchAPI\Query\SearchParamBuilder
 * @group elasticsearch_connector
 */
class SearchParamBuilderTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::buildSearchParams
   */
  public function testBuildSearchParams() {
    // Test 'Direct query' parse mode.
    $expected_direct = [
      'multi_match' => [
        'query' => 'bar',
        'fields' => ['foo^'],
      ],
    ];
    $this->buildSearchParams('direct', $expected_direct);

    // Test 'Multiple words' (terms) parse mode.
    $expected_terms = [
      'query_string' => [
        'query' => 'bar~',
        'fields' => ['foo^'],
      ],
    ];
    $this->buildSearchParams('terms', $expected_terms);
  }

  /**
   * Utility to test search parameters.
   *
   * @param string $parse_mode_id
   *   Parse mode ID.
   * @param array $expected
   *   Array of search query parameters expected.
   */
  public function buildSearchParams($parse_mode_id, $expected) {
    $backend = $this->prophesize(ElasticSearchBackend::class);
    $backend->getFuzziness()
      ->willReturn('auto');

    $server = $this->prophesize(Server::class);
    $server->getBackend()
      ->willReturn($backend->reveal());

    $indexId = "foo";
    $index = $this->prophesize(IndexInterface::class);
    $index->id()
      ->willReturn($indexId);
    $index->getFulltextFields()
      ->willReturn(['foo', 'bar', 'baz']);
    $index->getServerInstance()
      ->willReturn($server->reveal());

    $builder = new SearchParamBuilder();

    $parse_mode = $this->createMock(ParseModeInterface::class);
    $parse_mode->expects($this->once())
      ->method('getPluginId')
      ->willReturn($parse_mode_id);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getIndex')
      ->willReturn($index->reveal());
    $query->expects($this->once())
      ->method('getKeys')
      ->willReturn(['bar']);
    $query->method('getOriginalKeys')
      ->willReturn('bar');
    $query->expects($this->once())
      ->method('getFulltextFields')
      ->willReturn(['foo']);
    $query->expects($this->once())
      ->method('getParseMode')
      ->willReturn($parse_mode);

    $field1 = $this->prophesize(Field::class);
    $field1->getFieldIdentifier()
      ->willReturn('foo');
    $field1->getBoost()
      ->willReturn(NULL);

    $indexFields = ['foo' => $field1->reveal()];

    $settings = ['fuzziness' => 'auto'];

    $searchParams = $builder->buildSearchParams($query, $indexFields, $settings);

    $this->assertEquals($expected, $searchParams);
  }

  /**
   * @covers ::buildSearchString
   * @dataProvider buildSearchStringDataProvider
   */
  public function testBuildSearchString($keys, $fuzziness, $expected) {

    $searchStringBuilder = new SearchParamBuilder();

    $output = $searchStringBuilder->buildSearchString($keys, $fuzziness);
    $this->assertEquals($expected, (string) $output);
  }

  /**
   * PHPUnit data provider for tests.
   *
   * @return array
   *   The data.
   */
  public static function buildSearchStringDataProvider(): array {
    // @codingStandardsIgnoreStart
    return [
      'normal keywords' => [
        'keys' => [
          'foo',
          'bar',
          '#conjunction' => Query::OP_AND,
        ],
        "fuzziness" => "auto",
        'expected' => '(foo~ AND bar~)',
      ],
      'quoted phrase' => [
        'keys' => [
          'cogito ergo sum',
        ],
        "fuzziness" => "auto",
        'expected' => '"cogito ergo sum"~',
      ],
      'single-word quotes' => [
        'keys' => [
          'foo',
        ],
        "fuzziness" => "auto",
        'expected' => 'foo~',
      ],
      'negated keyword' => [
        'keys' => [
          [
            '#negation' => TRUE,
            'foo',
          ],
        ],
        "fuzziness" => NULL,
        'expected' => '-foo',
      ],
      'negated phrase' => [
        'keys' => [
          [
            '#negation' => TRUE,
            'cogito ergo sum',
          ],
        ],
        "fuzziness" => NULL,
        'expected' => '-"cogito ergo sum"',
      ],
      'keywords with stand-alone dash' => [
        'keys' => [
          'foo - bar',
        ],
        "fuzziness" => NULL,
        'expected' => '"foo \- bar"',
      ],
      'really complicated search' => [
        'keys' => [
          '#conjunction' => Query::OP_AND,
          'pos',
          [
            '#negation' => TRUE,
            'neg',
          ],
          'quoted pos with -minus',
          [
            '#negation' => TRUE,
            'quoted neg',
          ],
        ],
        "fuzziness" => NULL,
        'expected' => '(pos AND -neg AND "quoted pos with \-minus" AND -"quoted neg")',
      ],
      'multi-byte space' => [
        'keys' => [
          '#conjunction' => Query::OP_AND,
          '神奈川県',
          '連携',
        ],
        "fuzziness" => NULL,
        'expected' => '(神奈川県 AND 連携)',
      ],
      'nested search' => [
        'keys' => [
          '#conjunction' => Query::OP_AND,
          'foo',
          'whizbang' => [
            'keys' => [
              'whiz',
              [
                'bang',
                '#negation' => TRUE,
              ],
            ],
          ],
        ],
        "fuzziness" => NULL,
        'expected' => '(foo AND (whiz OR -bang))',
      ],
    ];
    // @codingStandardsIgnoreEnd
  }

}
