<?php

namespace Drupal\Tests\message_subscribe_email\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\message_subscribe_email\Manager;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Unit tests for the message subscribe email manager utility class.
 *
 * @coversDefaultClass \Drupal\message_subscribe_email\Manager
 *
 * @group message_subscribe_email
 */
class ManagerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests the flag retrieval.
   *
   * @param array $expected
   *   The expected flags.
   * @param array $flags
   *   The available flags for the flag service.
   *
   * @covers ::getFlags
   *
   * @dataProvider providerTestGetFlags
   */
  public function testGetFlags(array $expected, array $flags) {
    $flag_service = $this->prophesize(FlagServiceInterface::class);
    $flag_service->getAllFlags()->willReturn($flags);

    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('flag_prefix')->willReturn('non_standard_prefix');
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('message_subscribe_email.settings')->willReturn($config->reveal());

    $manager = new Manager($flag_service->reveal(), $config_factory->reveal());
    $this->assertEquals($expected, $manager->getFlags());
  }

  /**
   * Data provider for testGetFlags().
   *
   * @return array
   *   An array of arguments for self::testGetFlags().
   */
  public function providerTestGetFlags() {
    // No flags.
    $return[] = [[], []];

    // No matching flags.
    $flag = $this->prophesize(FlagInterface::class)->reveal();
    $return[] = [[], ['foo_flag' => $flag]];

    // A few matching flags.
    $return[] = [
      [
        'non_standard_prefix_one' => $flag,
        'non_standard_prefix_two' => $flag,
      ],
      [
        'foo_flag' => $flag,
        'non_standard_prefix_one' => $flag,
        'non_standard_prefix_two' => $flag,
      ],
    ];

    return $return;
  }

}
