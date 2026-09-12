<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Base class for ai_guardrail plugins.
 */
abstract class AiGuardrailPluginBase extends PluginBase implements AiGuardrailInterface {

  // Replaces injected services with their IDs when serialized.
  //
  // Guardrail plugin instances are reachable from the guardrail entity form,
  // whose `#ajax` guardrail select forces Drupal to serialize the form and its
  // form state into the form cache. Without this trait, any service held on a
  // guardrail plugin is serialized along with it, dragging in the container,
  // the database connection and closures, which makes the form cache write
  // fail and prevents the settings subform from ever loading.
  //
  // Subclasses must declare service properties as `protected` and not
  // `readonly`: the trait's __sleep() calls get_object_vars() from this class's
  // scope, so it cannot see subclass private properties, and its __wakeup()
  // reassigns the property, which a readonly property forbids. This is
  // documented for module developers in
  // docs/developers/guardrails/custom_guardrail.md, under "Injecting
  // Services".
  use DependencySerializationTrait;

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
