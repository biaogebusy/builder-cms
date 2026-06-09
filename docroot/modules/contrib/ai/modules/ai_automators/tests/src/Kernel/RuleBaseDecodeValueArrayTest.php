<?php

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_automators\PluginBaseClasses\RuleBase;

/**
 * Regression tests for RuleBase::decodeValueArray().
 *
 * @group ai_automators
 */
class RuleBaseDecodeValueArrayTest extends KernelTestBase {

  /**
   * Modules to enable before running the tests.
   *
   * @var array
   */
  protected static $modules = ['system', 'file', 'user', 'ai', 'token', 'ai_automators'];

  /**
   * Ensures fallback array decoding returns all entries.
   */
  public function testDecodeValueArrayReturnsAllFallbackValues() {
    $rule = $this->container
      ->get('plugin.manager.ai_automator')
      ->createInstance('llm_list_string');
    $this->assertInstanceOf(RuleBase::class, $rule);

    $this->assertSame(
      ['alpha', 'beta', 'gamma'],
      $rule->decodeValueArray([
        ['first' => 'alpha'],
        ['second' => 'beta'],
        ['third' => 'gamma'],
      ])
    );
  }

}
