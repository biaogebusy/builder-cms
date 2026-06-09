<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\AiFunctionCall\Derivative;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;

/**
 * This tests the action plugin deriver.
 *
 * @coversDefaultClass \Drupal\ai\Plugin\AiFunctionCall\Derivative\ActionPluginDeriver
 *
 * @group ai
 */
class ActionPluginDeriverTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'key',
    'file',
    'system',
    'node',
    'media',
    'comment',
    'user',
    'text',
    'field',
    'field_ui',
  ];

  /**
   * Test to get the action definitions.
   */
  public function testActionDefinitions(): void {
    // Get the media function call definition.
    $definition = \Drupal::service('plugin.manager.ai.function_calls')->getDefinition('action_plugin:entity:unpublish_action:media');
    $property = $definition['context_definitions']['entity:media'];
    // Check that the description is "Entity (e.g. media:123)".
    $this->assertEquals(new TranslatableMarkup('Entity (e.g. media:123)'), $property->getDescription());

    // Get the url function call definition.
    $definition = \Drupal::service('plugin.manager.ai.function_calls')->getDefinition('action_plugin:action_goto_action');
    $property = $definition['context_definitions']['url'];
    // Check that the description adds info about the URL format.
    $this->assertEquals(new TranslatableMarkup('(e.g. /node/123 or https://example.com)'), $property->getDescription());

    // Get the message function call definition.
    $definition = \Drupal::service('plugin.manager.ai.function_calls')->getDefinition('action_plugin:action_message_action');
    $property = $definition['context_definitions']['message'];
    // Check that the description adds info about the message.
    $this->assertEquals(new TranslatableMarkup('(e.g. The action was successful!)'), $property->getDescription());
  }

}
