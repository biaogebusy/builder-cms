<?php

declare(strict_types=1);

namespace Drupal\Tests\charts\Unit;

use Drupal\Tests\UnitTestCase;

require_once __DIR__ . '/../../../charts.module';

/**
 * Tests charts_views_data.
 *
 * @group charts
 */
class HookViewsDataTest extends UnitTestCase {

  /**
   * Tests charts_views_data().
   */
  public function testViewsData() {

    $data = charts_views_data();

    $this->assertIsArray($data);
    $this->assertCount(1, $data);
    $this->assertArrayHasKey('charts_fields', $data);
    $this->assertCount(5, $data['charts_fields']);
    $this->assertArrayHasKey('table', $data['charts_fields']);
    $this->assertArrayHasKey('field_charts_fields_scatter', $data['charts_fields']);
    $this->assertArrayHasKey('field_charts_fields_bubble', $data['charts_fields']);
    $this->assertArrayHasKey('field_charts_numeric_array', $data['charts_fields']);
    $this->assertArrayHasKey('field_exposed_chart_type', $data['charts_fields']);
  }

}
