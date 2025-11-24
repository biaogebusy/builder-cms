<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\elasticsearch_connector\SearchAPI\FieldMapper;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\DataTypeHelperInterface;
use Drupal\search_api\Utility\FieldsHelper;
use Drupal\search_api\Utility\ThemeSwitcherInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the field mapper.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\SearchAPI\FieldMapper
 * @group elasticsearch_connector
 */
class FieldMapperTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::mapFieldParams
   */
  public function testMapFieldParams() {

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityFieldManager = $this->prophesize(EntityFieldManagerInterface::class);
    $entityTypeBundleInfo = $this->prophesize(EntityTypeBundleInfoInterface::class);
    $dataTypeHelper = $this->prophesize(DataTypeHelperInterface::class);
    $themeSwitcher = $this->prophesize(ThemeSwitcherInterface::class);
    $fieldsHelper = new FieldsHelper($entityTypeManager->reveal(), $entityFieldManager->reveal(), $entityTypeBundleInfo->reveal(), $dataTypeHelper->reveal(), $themeSwitcher->reveal());

    $event = $this->prophesize(FieldMappingEvent::class);
    $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
    $eventDispatcher->dispatch(Argument::any())->willReturn($event->reveal());
    $fieldMapper = new FieldMapper($fieldsHelper, $eventDispatcher->reveal());

    $index = $this->prophesize(IndexInterface::class);

    $field1Id = $this->randomMachineName(8);
    $field1 = (new Field($index->reveal(), $field1Id))
      ->setType("text");

    $field2Id = $this->randomMachineName(8);
    $field2 = (new Field($index->reveal(), $field2Id))
      ->setType("string");

    $fields = [
      $field1Id => $field1,
      $field2Id => $field2,
    ];

    $indexId = $this->randomMachineName();
    $index->id()->willReturn($indexId);
    $index->getFields()->willReturn($fields);

    $params = $fieldMapper->mapFieldParams($indexId, $index->reveal());

    $expectedParams = [
      "index" => $indexId,
      "body" => [
        "properties" => [
          "id" => [
            "type" => "keyword",
            "index" => "true",
          ],
          $field1Id => [
            'type' => 'text',
            'fields' => [
              'keyword' => ['type' => 'keyword', 'ignore_above' => 256],
            ],
          ],
          $field2Id => [
            'type' => 'keyword',
          ],
          'search_api_id' => [
            "type" => "keyword",
          ],
          'search_api_datasource' => [
            "type" => "keyword",
          ],
          'search_api_language' => [
            "type" => "keyword",
          ],
        ],
      ],
    ];
    $this->assertEquals($expectedParams, $params);

  }

}
