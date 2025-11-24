<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI\Query;

use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\SearchAPI\Query\FacetResultParser;
use Drupal\elasticsearch_connector\SearchAPI\Query\QueryResultParser;
use Drupal\elasticsearch_connector\SearchAPI\Query\SpellCheckResultParser;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\Item;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSet;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Prophecy\Argument;

/**
 * Tests the query result parser.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\SearchAPI\Query\QueryResultParser
 * @group elasticsearch_connector
 */
class QueryResultParserTest extends UnitTestCase {

  /**
   * @covers ::parseResult
   */
  public function testParseResult() {

    $indexId = "index_" . $this->randomMachineName();
    $index = $this->prophesize(IndexInterface::class);
    $index->id()->willReturn($indexId);

    $item1Id = "item_" . $this->randomMachineName();
    $item1 = new Item($index->reveal(), $item1Id);

    $item2Id = "item_" . $this->randomMachineName();
    $item2 = new Item($index->reveal(), $item2Id);

    $field1Id = "field_" . $this->randomMachineName();
    $field1 = (new Field($index->reveal(), $field1Id))
      ->setType("string")
      ->setValues(["foo"])
      ->setDatasourceId('entity');

    $field2Id = "field_" . $this->randomMachineName();
    $field2 = (new Field($index->reveal(), $field2Id))
      ->setType("string")
      ->setValues(["bar"])
      ->setDatasourceId('entity');

    $fieldsHelper = $this->prophesize(FieldsHelperInterface::class);
    $fieldsHelper->createItem(Argument::any(), Argument::any())
      ->willReturn($item1, $item2);
    $fieldsHelper->createField(Argument::any(), Argument::any(), Argument::cetera())
      ->willReturn($field1, $field2);

    $query = $this->prophesize(QueryInterface::class);
    $results = new ResultSet($query->reveal());

    $query->getIndex()->willReturn($index->reveal());
    $query->getResults()->willReturn($results);

    $response = [
      "hits" => [
        "total" => [
          "value" => 2,
        ],
        "hits" => [
          [
            "_id" => $item1Id,
            "_score" => 1.333,
            "_source" => [$field1Id => "foo"],
          ],
          [
            "_id" => $item2Id,
            "_score" => 1.0,
            "_source" => [$field2Id => "bar"],
          ],
        ],
      ],
    ];

    $facetResultParser = $this->prophesize(FacetResultParser::class);
    $spellCheckResultParser = $this->prophesize(SpellCheckResultParser::class);

    $parser = new QueryResultParser($fieldsHelper->reveal(), $facetResultParser->reveal(), $spellCheckResultParser->reveal());

    $results = $parser->parseResult($query->reveal(), $response);

    $this->assertNotNull($results);
    $this->assertEquals($response, $results->getExtraData("elasticsearch_response"));
    $this->assertEquals(2, $results->getResultCount());

    $items = $results->getResultItems();
    $this->assertCount(2, $items);

    $foundItem1 = $items[$item1Id];
    $foundFields1 = $foundItem1->getFields(FALSE);
    $this->assertCount(1, $foundFields1);

    $this->assertNotNull($foundFields1[$field1Id]);

    $foundField1 = $foundFields1[$field1Id];
    $values1 = $foundField1->getValues();
    $this->assertCount(1, $values1);
    $this->assertEquals("foo", $values1[0]);

  }

}
