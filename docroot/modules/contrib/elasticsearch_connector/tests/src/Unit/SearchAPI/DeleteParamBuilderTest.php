<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI;

use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\Event\DeleteParamsEvent;
use Drupal\elasticsearch_connector\SearchAPI\DeleteParamBuilder;
use Drupal\search_api\IndexInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the delete param builder.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\SearchAPI\DeleteParamBuilder
 * @group elasticsearch_connector
 */
class DeleteParamBuilderTest extends UnitTestCase {

  use ProphecyTrait;
  /**
   * {@inheritdoc}
   */
  protected static $modules = ['search_api', 'elasticsearch_connector'];

  /**
   * @covers ::buildDeleteParams
   */
  public function testbuildDeleteParams() {
    $index = $this->prophesize(IndexInterface::class);
    $indexId = "index_" . $this->randomMachineName();
    $index->id()->willReturn($indexId);

    $item1Id = $this->randomMachineName();
    $item2Id = $this->randomMachineName();

    $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);

    $expectedParams = [
      'index' => $indexId,
      'body' => [
        [
          'delete' => ['_id' => $item1Id, '_index' => $indexId],
        ],
        [
          'delete' => ['_id' => $item2Id, '_index' => $indexId],
        ],
      ],
    ];

    $deleteParamsEvent = new DeleteParamsEvent($indexId, $expectedParams);
    $eventDispatcher->dispatch($deleteParamsEvent)
      ->shouldBeCalledTimes(1);

    $paramBuilder = new DeleteParamBuilder($eventDispatcher->reveal());

    $params = $paramBuilder->buildDeleteParams($indexId, [$item1Id, $item2Id]);

    $this->assertEquals($expectedParams, $params);
  }

}
