<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI\Query;

use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\SearchAPI\Query\SpellCheckBuilder;
use Drupal\search_api\Query\QueryInterface;

/**
 * Tests the spell check builder.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\SearchAPI\Query\SpellCheckBuilder
 * @group elasticsearch_connector
 */
class SpellCheckBuilderTest extends UnitTestCase {

  /**
   * @covers ::setSpellCheckQuery
   */
  public function testSetSpellCheckQuery() {
    $builder = new SpellCheckBuilder();

    // We can't use prophecy here like the other tests in this module do as
    // getFulltextFields returns an array by reference, and prophecy doesn't
    // allow that.
    $query = $this->createMock(QueryInterface::class);
    $query->method('getFulltextFields')->willReturn([
      'field1' => 'field1',
      'field2' => 'field2',
    ]);
    $query->method('getOption')->willReturn([
      'keys' => ['keys1', 'keys2'],
      'count' => 1,
    ]);

    $expected = [
      'field1' => [
        'text' => 'keys1 keys2',
        'term' => [
          'field' => 'field1',
          'size' => 1,
        ],
      ],
      'field2' => [
        'text' => 'keys1 keys2',
        'term' => [
          'field' => 'field2',
          'size' => 1,
        ],
      ],
    ];

    $this->assertEquals($expected, $builder->setSpellCheckQuery($query));
  }

}
