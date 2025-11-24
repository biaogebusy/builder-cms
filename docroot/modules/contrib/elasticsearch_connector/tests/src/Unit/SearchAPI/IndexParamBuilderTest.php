<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\elasticsearch_connector\SearchAPI\IndexParamBuilder;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\Item;
use Drupal\search_api\Utility\DataTypeHelperInterface;
use Drupal\search_api\Utility\FieldsHelper;
use Drupal\search_api\Utility\ThemeSwitcherInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the index param builder.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\SearchAPI\IndexParamBuilder
 * @group elasticsearch_connector
 */
class IndexParamBuilderTest extends UnitTestCase {

  use ProphecyTrait;
  /**
   * {@inheritdoc}
   */
  protected static $modules = ['search_api', 'elasticsearch_connector'];

  /**
   * @covers ::buildIndexParams
   */
  public function testbuildIndexParams() {
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityFieldManager = $this->prophesize(EntityFieldManagerInterface::class);
    $entityTypeBundleInfo = $this->prophesize(EntityTypeBundleInfoInterface::class);
    $dataTypeHelper = $this->prophesize(DataTypeHelperInterface::class);
    $themeSwitcher = $this->prophesize(ThemeSwitcherInterface::class);
    $fieldsHelper = new FieldsHelper($entityTypeManager->reveal(), $entityFieldManager->reveal(), $entityTypeBundleInfo->reveal(), $dataTypeHelper->reveal(), $themeSwitcher->reveal());

    $event = $this->prophesize(IndexParamsEvent::class);
    $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
    $eventDispatcher->dispatch(Argument::any())->willReturn($event->reveal());

    $paramBuilder = new IndexParamBuilder($fieldsHelper, $eventDispatcher->reveal());

    $index = $this->prophesize(IndexInterface::class);
    $indexId = "index_" . $this->randomMachineName();
    $index->id()->willReturn($indexId);

    $field1Id = "field1_" . $this->randomMachineName(8);
    $field2Id = "field2_" . $this->randomMachineName(8);

    $item1Id = "item1_" . $this->randomMachineName();
    $item1 = (new Item($index->reveal(), $item1Id))
      ->setFieldsExtracted(TRUE)
      ->setLanguage("en")
      ->setField($field1Id, (new Field($index->reveal(), $field1Id))
        ->setType("string")
        ->setValues(["foo"])
        ->setDatasourceId('entity'))
      ->setField($field2Id, (new Field($index->reveal(), $field2Id))
        ->setType("string")
        ->setValues(["bar"])
        ->setDatasourceId('entity'));

    $item2Id = "item2_" . $this->randomMachineName();
    $item2 = (new Item($index->reveal(), $item2Id))
      ->setFieldsExtracted(TRUE)
      ->setLanguage("en")
      ->setField($field1Id, (new Field($index->reveal(), $field1Id))
        ->setType("string")
        ->setValues(["bar"])
        ->setDatasourceId('entity'));

    $items = [
      $item1Id => $item1,
      $item2Id => $item2,
    ];

    $params = $paramBuilder->buildIndexParams($indexId, $index->reveal(), $items);

    $expectedParams = [
      'body' => [
        [
          'index' => ['_id' => $item1Id, '_index' => $indexId],
        ],
        [
          $field1Id => ['foo'],
          $field2Id => ['bar'],
          'search_api_id' => [$item1Id],
          'search_api_datasource' => [''],
          'search_api_language' => ['en'],
        ],
        [
          'index' => ['_id' => $item2Id, '_index' => $indexId],
        ],
        [
          $field1Id => ['bar'],
          'search_api_id' => [$item2Id],
          'search_api_datasource' => [''],
          'search_api_language' => ['en'],
        ],
      ],
    ];

    $this->assertEquals($expectedParams, $params);
  }

}
