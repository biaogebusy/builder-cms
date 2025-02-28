<?php

namespace Drupal\Tests\migrate_plus\Unit\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Plugin\migrate\process\StrReplace;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;

/**
 * Tests the str replace process plugin.
 *
 * @group migrate
 * @coversDefaultClass \Drupal\migrate_plus\Plugin\migrate\process\StrReplace
 */
class StrReplaceTest extends MigrateProcessTestCase {

  /**
   * Test for a simple str_replace string.
   */
  public function testStrReplace(): void {
    $value = 'vero eos et accusam et justo vero';
    $configuration['search'] = 'et';
    $configuration['replace'] = 'that';
    $plugin = new StrReplace($configuration, 'str_replace', []);
    $actual = $plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationproperty');
    $this->assertSame('vero eos that accusam that justo vero', $actual);

  }

  /**
   * Test for case insensitive searches.
   */
  public function testStrIreplace(): void {
    $value = 'VERO eos et accusam et justo vero';
    $configuration['search'] = 'vero';
    $configuration['replace'] = 'that';
    $configuration['case_insensitive'] = TRUE;
    $plugin = new StrReplace($configuration, 'str_replace', []);
    $actual = $plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationproperty');
    $this->assertSame('that eos et accusam et justo that', $actual);

  }

  /**
   * Test for regular expressions.
   */
  public function testPregReplace(): void {
    $value = 'vero eos et 123 accusam et justo 123 duo';
    $configuration['search'] = '/[0-9]{3}/';
    $configuration['replace'] = 'the';
    $configuration['regex'] = TRUE;
    $plugin = new StrReplace($configuration, 'str_replace', []);
    $actual = $plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationproperty');
    $this->assertSame('vero eos et the accusam et justo the duo', $actual);
  }

  /**
   * Test for InvalidArgumentException for "search" configuration.
   */
  public function testSearchInvalidArgumentException(): void {
    $configuration['replace'] = 'that';
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The "search" must be set.');
    new StrReplace($configuration, 'str_replace', []);
  }

  /**
   * Test for InvalidArgumentException for "replace" configuration.
   */
  public function testReplaceInvalidArgumentException(): void {
    $configuration['search'] = 'et';
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The "replace" must be set.');
    new StrReplace($configuration, 'str_replace', []);
  }

  /**
   * Test for multiple.
   */
  public function testIsMultiple(): void {
    $value = [
      'vero eos et accusam et justo vero',
      'et eos vero accusam vero justo et',
    ];

    $expected = [
      'vero eos that accusam that justo vero',
      'that eos vero accusam vero justo that',
    ];
    $configuration['search'] = 'et';
    $configuration['replace'] = 'that';
    $plugin = new StrReplace($configuration, 'str_replace', []);
    $actual = $plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationproperty');
    $this->assertSame($expected, $actual);

    $this->assertTrue($plugin->multiple());
  }

}
