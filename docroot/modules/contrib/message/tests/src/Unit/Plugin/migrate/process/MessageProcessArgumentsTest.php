<?php

namespace Drupal\Tests\message\Unit\Plugin\migrate\process;

use Drupal\message\Plugin\migrate\process\MessageProcessArguments;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Unit tests for the d7_message_arguments process plugin.
 *
 * @coversDefaultClass \Drupal\message\Plugin\migrate\process\MessageProcessArguments
 *
 * @group Message
 */
class MessageProcessArgumentsTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests transforming serialized D7 message arguments.
   *
   * @covers ::transform
   */
  public function testTransform(): void {
    $plugin = new MessageProcessArguments([], 'd7_message_arguments', []);
    $executable = $this->prophesize(MigrateExecutableInterface::class)->reveal();
    $row = $this->prophesize(Row::class)->reveal();

    $arguments = serialize([
      '!name' => 'Alice',
      '@place' => 'Wonderland',
      '%count' => '3',
    ]);

    $result = $plugin->transform([$arguments], $executable, $row, 'arguments');

    $this->assertEquals([
      '@name' => 'Alice',
      '@place' => 'Wonderland',
      '%count' => '3',
    ], $result);
  }

  /**
   * Tests transforming an empty arguments array.
   *
   * @covers ::transform
   */
  public function testTransformEmpty(): void {
    $plugin = new MessageProcessArguments([], 'd7_message_arguments', []);
    $executable = $this->prophesize(MigrateExecutableInterface::class)->reveal();
    $row = $this->prophesize(Row::class)->reveal();

    $result = $plugin->transform([serialize([])], $executable, $row, 'arguments');
    $this->assertEquals([], $result);
  }

}
