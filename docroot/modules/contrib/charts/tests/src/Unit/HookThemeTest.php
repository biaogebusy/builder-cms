<?php

declare(strict_types=1);

namespace Drupal\Tests\charts\Unit;

use Drupal\Tests\UnitTestCase;

require_once __DIR__ . '/../../../charts.module';

/**
 * Tests charts_theme.
 *
 * @group charts
 */
class HookThemeTest extends UnitTestCase {

  /**
   * Tests charts_theme().
   */
  public function testViewsData() {
    $existing = NULL;
    $type = NULL;
    $theme = NULL;
    $path = NULL;
    $data = charts_theme($existing, $type, $theme, $path);

    $this->assertIsArray($data);
    $this->assertCount(1, $data);
    $this->assertArrayHasKey('charts_chart', $data);
    $this->assertCount(1, $data['charts_chart']);
    $this->assertArrayHasKey('render element', $data['charts_chart']);
    $this->assertEquals('element', $data['charts_chart']['render element']);
  }

}
