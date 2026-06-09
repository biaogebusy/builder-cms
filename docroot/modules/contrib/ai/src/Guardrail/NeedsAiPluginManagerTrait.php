<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail;

use Drupal\ai\AiProviderPluginManager;

/**
 * A trait for classes that need access to the AI plugin manager.
 */
trait NeedsAiPluginManagerTrait {

  /**
   * The AI plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiPluginManager;

  /**
   * Sets the AI plugin manager.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiPluginManager
   *   The AI plugin manager.
   */
  public function setAiPluginManager(AiProviderPluginManager $aiPluginManager): void {
    $this->aiPluginManager = $aiPluginManager;
  }

  /**
   * Returns the AI plugin manager.
   *
   * @return \Drupal\ai\AiProviderPluginManager
   *   The AI plugin manager.
   */
  public function getAiPluginManager(): AiProviderPluginManager {
    return $this->aiPluginManager;
  }

}
