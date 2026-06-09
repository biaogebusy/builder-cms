<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a guardrail set entity type.
 */
interface AiGuardrailSetInterface extends ConfigEntityInterface {

  /**
   * Gets the guardrails that run before the generation of AI content.
   */
  public function getPreGenerateGuardrails();

  /**
   * Gets the guardrails that run after the generation of AI content.
   */
  public function getPostGenerateGuardrails();

  /**
   * Gets the stop threshold for the guardrail set.
   */
  public function getStopThreshold(): float;

}
