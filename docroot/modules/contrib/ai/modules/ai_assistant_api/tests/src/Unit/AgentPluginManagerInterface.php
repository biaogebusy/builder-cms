<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_assistant_api\Unit;

/**
 * Test-only interface for the AI agent plugin manager.
 *
 * AgentRunner receives the plugin manager as mixed, so there is no real
 * interface to mock against. This local stub declares the single method that
 * AgentRunner calls (createInstance) so tests can mock it without using the
 * deprecated addMethods() API.
 */
interface AgentPluginManagerInterface {

  /**
   * Creates a plugin instance by ID.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $configuration
   *   (optional) Plugin configuration.
   *
   * @return object
   *   The plugin instance.
   */
  public function createInstance(string $plugin_id, array $configuration = []): object;

}
