<?php

namespace Drupal\Tests\migrate_plus\Unit\process;

use Drupal\Component\Transliteration\PhpTransliteration;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Plugin\migrate\process\Transliteration;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;

/**
 * Tests the transliteration process plugin.
 *
 * @group migrate_plus
 * @group legacy
 */
class TransliterationTest extends MigrateProcessTestCase {

  /**
   * A transliteration instance.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliteration;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->transliteration = new PhpTransliteration();
    $this->row = $this->getMockBuilder(Row::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->migrateExecutable = $this->getMockBuilder(MigrateExecutableInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    parent::setUp();
  }

  /**
   * Tests transliteration transformation of non-alphanumeric characters.
   */
  public function testTransform(): void {
    $actual = '9000004351_53494854_Spøgelsesjægerneáéö';
    $expected_result = '9000004351_53494854_Spogelsesjaegerneaeo';

    $plugin = new Transliteration([], 'transliteration', [], $this->transliteration);
    $value = $plugin->transform($actual, $this->migrateExecutable, $this->row, 'destinationproperty');
    $this->assertEquals($expected_result, $value);
  }

  /**
   * Tests deprecation notice of Transliteration process plugin.
   *
   * @group legacy
   */
  public function testDeprecationMessage() {
    $this->expectDeprecation("Drupal\migrate_plus\Plugin\migrate\process\Transliteration is deprecated in migrate_plus:8.x-5.3 and is removed from migrate_plus:6.0.0. Use Drupal\migrate_plus\Plugin\migrate\process\Service process plugin instead. See https://www.drupal.org/node/3255994");
    new Transliteration([], 'transliteration', [], $this->transliteration);
  }

}
