<?php

declare(strict_types=1);

namespace Drupal\Tests\tour\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\tour\TipPluginBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TipPluginBase class.
 */
#[CoversClass(TipPluginBase::class)]
#[Group('tour')]
class TipPluginBaseTest extends UnitTestCase {

  /**
   * Tests getLocation().
   */
  public function testGetLocationAssertion() {
    $base_plugin = $this->getMockBuilder(TipPluginBase::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();

    $base_plugin->set('position', 'right');
    $this->assertSame('right', $base_plugin->getLocation());

    $base_plugin->set('position', 'not_valid');
    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessage('not_valid is not a valid Tour Tip position value');
    $base_plugin->getLocation();
  }

}
