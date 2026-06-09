<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail;

use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for ai_guardrail plugins.
 */
abstract class AiGuardrailPluginBase extends PluginBase implements AiGuardrailInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return TRUE;
  }

}
