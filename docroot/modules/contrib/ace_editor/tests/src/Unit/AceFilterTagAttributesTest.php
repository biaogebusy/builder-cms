<?php

declare(strict_types=1);

namespace Drupal\Tests\ace_editor\Unit;

use Drupal\ace_editor\Plugin\Filter\AceFilter;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the pure parsing helpers of the Ace Filter.
 *
 * AceFilter::tagAttributes() extracts the attributes of an <ace> tag (mapping
 * hyphenated attribute names to the underscore settings keys and casting
 * boolean-like values to integers) and AceFilter::strReplaceOnce() replaces
 * only the first occurrence of a snippet. Both are pure and need no container.
 *
 * @group ace_editor
 * @coversDefaultClass \Drupal\ace_editor\Plugin\Filter\AceFilter
 */
#[Group('ace_editor')]
class AceFilterTagAttributesTest extends UnitTestCase {

  /**
   * The filter instance under test.
   *
   * @var \Drupal\ace_editor\Plugin\Filter\AceFilter
   */
  protected AceFilter $filter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->filter = new AceFilter([], 'ace_filter', [
      'id' => 'ace_filter',
      'provider' => 'ace_editor',
    ]);
  }

  /**
   * String attributes are returned keyed by name.
   *
   * @covers ::tagAttributes
   */
  public function testStringAttributes(): void {
    $tag = '<ace theme="twilight" syntax="javascript">code</ace>';
    $this->assertSame(
      ['theme' => 'twilight', 'syntax' => 'javascript'],
      $this->filter->tagAttributes('ace', $tag)
    );
  }

  /**
   * Hyphenated names map to underscore keys and booleans cast to integers.
   *
   * @covers ::tagAttributes
   */
  public function testHyphenatedAndBooleanAttributes(): void {
    $tag = '<ace print-margin="1" line-numbers="0">code</ace>';
    $this->assertSame(
      ['print_margin' => 1, 'line_numbers' => 0],
      $this->filter->tagAttributes('ace', $tag)
    );
  }

  /**
   * TRUE/FALSE keep their meaning instead of being inverted by intval().
   *
   * @covers ::tagAttributes
   */
  public function testWordBooleanAttributes(): void {
    $this->assertSame(
      ['print_margin' => 1, 'line_numbers' => 0],
      $this->filter->tagAttributes('ace', '<ace print-margin="TRUE" line-numbers="false">code</ace>')
    );
  }

  /**
   * A tag with no attributes yields an empty set, not FALSE.
   *
   * The caller iterates over the result, so returning FALSE raised a warning on
   * every render of a bare tag, which is the documented basic usage.
   *
   * @covers ::tagAttributes
   */
  public function testNoAttributesReturnsAnEmptyArray(): void {
    $this->assertSame([], $this->filter->tagAttributes('ace', '<ace>code</ace>'));
    $this->assertSame([], $this->filter->tagAttributes('ace', '<ace >code</ace>'));
  }

  /**
   * Only the first occurrence of the needle is replaced.
   *
   * @covers ::strReplaceOnce
   */
  public function testStrReplaceOnceReplacesFirst(): void {
    $this->assertSame(
      'bar foo',
      $this->filter->strReplaceOnce('foo', 'bar', 'foo foo')
    );
  }

  /**
   * A missing needle leaves the haystack unchanged.
   *
   * @covers ::strReplaceOnce
   */
  public function testStrReplaceOnceMissingNeedle(): void {
    $this->assertSame(
      'abc',
      $this->filter->strReplaceOnce('x', 'y', 'abc')
    );
  }

}
