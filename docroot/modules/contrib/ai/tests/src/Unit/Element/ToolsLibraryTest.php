<?php

namespace Drupal\Tests\ai\Unit\Element;

use Drupal\ai\Element\ToolsLibrary;
use Drupal\Core\Form\FormState;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the ai_tools_library form element.
 *
 * @group ai
 * @covers \Drupal\ai\Element\ToolsLibrary
 */
class ToolsLibraryTest extends TestCase {

  /**
   * Tests that tools ids are normalized to a list of ids.
   *
   * @param mixed $ids
   *   The raw ids, as submitted by the widget or stored in configuration.
   * @param array $expected
   *   The expected list of tools ids.
   *
   * @dataProvider providerProcessToolsIds
   */
  public function testProcessToolsIds($ids, array $expected): void {
    $this->assertSame($expected, ToolsLibrary::processToolsIds($ids));
  }

  /**
   * Data provider for ::testProcessToolsIds().
   *
   * @return array
   *   Test cases of raw ids and their expected normalized list.
   */
  public static function providerProcessToolsIds(): array {
    return [
      'empty string' => ['', []],
      'empty array' => [[], []],
      'null' => [NULL, []],
      'single id' => ['ai_agent:one', ['ai_agent:one']],
      'comma delimited' => ['ai_agent:one,ai_agent:two', ['ai_agent:one', 'ai_agent:two']],
      'trailing comma' => ['ai_agent:one,', ['ai_agent:one']],
      'leading comma' => [',ai_agent:one', ['ai_agent:one']],
      'empty value in the middle' => ['ai_agent:one,,ai_agent:two', ['ai_agent:one', 'ai_agent:two']],
      'list of ids' => [['ai_agent:one', 'ai_agent:two'], ['ai_agent:one', 'ai_agent:two']],
      'configuration map' => [
        ['ai_agent:one' => TRUE, 'ai_agent:two' => TRUE],
        ['ai_agent:one', 'ai_agent:two'],
      ],
      'configuration map with disabled tool' => [
        ['ai_agent:one' => TRUE, 'ai_agent:two' => FALSE],
        ['ai_agent:one'],
      ],
    ];
  }

  /**
   * Tests that an empty submission clears the selection.
   *
   * Removing the last remaining tool submits an empty string. That must not be
   * treated as "no input", otherwise #default_value is restored and the tool
   * can never be deleted.
   *
   * @see https://www.drupal.org/project/ai_agents/issues/3586058
   */
  public function testEmptySubmissionClearsTheSelection(): void {
    $form_state = new FormState();
    $element = ['#default_value' => ['ai_agent:one' => TRUE]];

    $this->assertSame([], ToolsLibrary::valueCallback($element, '', $form_state));
  }

  /**
   * Tests that a submitted selection is used and normalized.
   */
  public function testSubmittedSelectionIsUsed(): void {
    $form_state = new FormState();
    $element = ['#default_value' => ['ai_agent:one' => TRUE]];

    $this->assertSame(
      ['ai_agent:two', 'ai_agent:three'],
      ToolsLibrary::valueCallback($element, 'ai_agent:two,ai_agent:three', $form_state)
    );
  }

  /**
   * Tests that the default value is used when nothing was submitted.
   *
   * The configuration stores the selection as a map of tool id => enabled, so
   * the default value has to be normalized to a list of ids as well. Consumers
   * iterate over the value, and would otherwise read TRUE as a tool id.
   */
  public function testDefaultValueIsUsedWhenNothingWasSubmitted(): void {
    $form_state = new FormState();
    $element = ['#default_value' => ['ai_agent:one' => TRUE]];

    $this->assertSame(['ai_agent:one'], ToolsLibrary::valueCallback($element, FALSE, $form_state));
    $this->assertSame(['ai_agent:one'], ToolsLibrary::valueCallback($element, NULL, $form_state));
  }

  /**
   * Tests that a missing default value does not fail.
   */
  public function testMissingDefaultValue(): void {
    $form_state = new FormState();
    $element = [];

    $this->assertSame([], ToolsLibrary::valueCallback($element, FALSE, $form_state));
  }

}
