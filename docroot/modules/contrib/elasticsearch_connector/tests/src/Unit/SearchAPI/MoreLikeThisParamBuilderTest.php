<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\SearchAPI\MoreLikeThisParamBuilder;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

/**
 * Tests the More Like This param builder.
 *
 * @group elasticsearch_connector
 * @coversDefaultClass \Drupal\elasticsearch_connector\SearchAPI\MoreLikeThisParamBuilder
 */
class MoreLikeThisParamBuilderTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::buildMoreLikeThisQuery
   */
  public function testBuildMoreLikeThisQuery() {

    $typedData = $this->prophesize(ComplexDataInterface::class);

    $entity = $this->prophesize(EntityInterface::class);
    $entity->getTypedData()->willReturn($typedData->reveal());

    $entityTypeStorage = $this->prophesize(EntityStorageInterface::class);
    $entityTypeStorage->load(Argument::any())->willReturn($entity->reveal());

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('foo')
      ->willReturn($entityTypeStorage->reveal());

    $logger = $this->prophesize(LoggerInterface::class);
    $builder = new MoreLikeThisParamBuilder($entityTypeManager->reveal(), $logger->reveal());

    $itemId = "item_" . $this->randomMachineName();
    $options = [
      "id" => $itemId,
      "fields" => ["*"],
    ];

    $expectedParams = [
      'more_like_this' =>
        [
          'fields' => ['*'],
          'max_query_terms' => 1,
          'min_doc_freq' => 1,
          'min_term_freq' => 1,
          'like' => [
            ['_id' => 'test/123'],
          ],
        ],
    ];

    $datasource = $this->prophesize(DatasourceInterface::class);
    $datasource->getEntityTypeId()->willReturn("foo");
    $datasource->getPluginId()->willReturn("test");

    $datasource->getItemId(Argument::any())->willReturn("123");
    $datasources = [$datasource->reveal()];

    $index = $this->prophesize(IndexInterface::class);
    $index->getDatasources()->willReturn($datasources);

    $params = $builder->buildMoreLikeThisQuery($options, $index->reveal());

    $this->assertEquals($expectedParams, $params);
  }

  /**
   * @covers ::buildMoreLikeThisQuery
   */
  public function testValidate(): void {
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $logger = $this->prophesize(LoggerInterface::class);
    $logger->warning(Argument::containingString("Missing required keys"), Argument::any());
    $builder = new MoreLikeThisParamBuilder($entityTypeManager->reveal(), $logger->reveal());

    $index = $this->prophesize(IndexInterface::class);
    $params = $builder->buildMoreLikeThisQuery([], $index->reveal());
    $this->assertEmpty($params);
  }

}
