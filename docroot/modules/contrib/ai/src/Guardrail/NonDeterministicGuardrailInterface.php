<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail;

use Drupal\ai\AiProviderPluginManager;

/**
 * Interface for non-deterministic guardrails.
 *
 * A non-deterministic guardrail may produce different results
 * for the same input on different invocations. The AI plugin manager
 * is made available to such guardrails to allow them to leverage
 * AI capabilities as part of their processing logic.
 */
interface NonDeterministicGuardrailInterface {

  /**
   * Sets the AI plugin manager.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiPluginManager
   *   The AI plugin manager instance.
   */
  public function setAiPluginManager(AiProviderPluginManager $aiPluginManager): void;

  /**
   * Gets the AI plugin manager.
   *
   * @return \Drupal\ai\AiProviderPluginManager
   *   The AI plugin manager instance.
   */
  public function getAiPluginManager(): AiProviderPluginManager;

}
