<?php

declare(strict_types=1);

namespace Drupal\Tests\ace_editor\Unit;

use Drupal\ace_editor\Plugin\Field\FieldWidget\AceWidget;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the settings a widget instance publishes.
 *
 * The settings reach the browser in a data attribute, so only the keys of one
 * editor instance may pass: the module configuration also carries the option
 * lists of the settings form, 34 themes and 142 syntaxes.
 *
 * @coversDefaultClass \Drupal\ace_editor\Plugin\Field\FieldWidget\AceWidget
 *
 * @group ace_editor
 */
#[Group('ace_editor')]
class AceWidgetSettingsTest extends UnitTestCase {

  /**
   * A Gherkin field keeps its syntax, and drops the option lists.
   *
   * Ace ships a "gherkin" mode, so a field holding a .feature script is a
   * plain widget configuration: the syntax survives, and the maps do not.
   *
   * @covers ::filterSettings
   */
  public function testGherkinFieldSettings(): void {
    $filtered = AceWidget::filterSettings([
      'theme' => 'twilight',
      'syntax' => 'gherkin',
      'height' => '420px',
      'width' => '100%',
      'font_size' => '13pt',
      'line_numbers' => TRUE,
      'print_margins' => FALSE,
      'show_invisibles' => TRUE,
      'use_wrap_mode' => TRUE,
      'auto_complete' => FALSE,
      // Neither of these is a setting of an instance.
      'theme_list' => ['twilight' => 'Twilight'],
      'syntax_list' => ['gherkin' => 'Gherkin'],
      '_core' => ['default_config_hash' => 'abc'],
    ]);

    $this->assertSame('gherkin', $filtered['syntax']);
    $this->assertSame('twilight', $filtered['theme']);
    $this->assertTrue($filtered['show_invisibles']);
    $this->assertFalse($filtered['auto_complete']);

    $this->assertArrayNotHasKey('theme_list', $filtered);
    $this->assertArrayNotHasKey('syntax_list', $filtered);
    $this->assertArrayNotHasKey('_core', $filtered);
  }

  /**
   * The published settings are exactly the keys the JavaScript reads.
   *
   * @covers ::filterSettings
   */
  public function testOnlyKnownKeysArePublished(): void {
    $filtered = AceWidget::filterSettings([
      'syntax' => 'gherkin',
      'unexpected' => 'value',
      'third_party_settings' => ['foo' => 'bar'],
    ]);

    $this->assertSame(['syntax' => 'gherkin'], $filtered);
  }

  /**
   * A missing key stays missing rather than becoming NULL.
   *
   * The JavaScript falls back to its own defaults for an absent key, so an
   * explicit NULL would override that fallback with nothing.
   *
   * @covers ::filterSettings
   */
  public function testAbsentKeysAreNotInvented(): void {
    $filtered = AceWidget::filterSettings(['syntax' => 'gherkin']);

    $this->assertArrayNotHasKey('theme', $filtered);
    $this->assertArrayNotHasKey('height', $filtered);
    $this->assertCount(1, $filtered);
  }

  /**
   * The settings serialise to the attribute the behaviour parses.
   *
   * @covers ::filterSettings
   */
  public function testSettingsSerialiseForTheDataAttribute(): void {
    $filtered = AceWidget::filterSettings([
      'syntax' => 'gherkin',
      'theme' => 'twilight',
      'line_numbers' => TRUE,
    ]);

    $decoded = json_decode(json_encode($filtered), TRUE);

    $this->assertSame('gherkin', $decoded['syntax']);
    $this->assertTrue($decoded['line_numbers']);
  }

  /**
   * Empty settings publish nothing at all.
   *
   * @covers ::filterSettings
   */
  public function testEmptySettings(): void {
    $this->assertSame([], AceWidget::filterSettings([]));
  }

}
