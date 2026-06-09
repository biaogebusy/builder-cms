<?php

namespace Drupal\ai\OperationType;

use Drupal\ai\Entity\AiGuardrailModeEnum;
use Drupal\ai\Guardrail\AiGuardrailSetInterface;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;

/**
 * Base Input Interface class.
 */
interface InputInterface {

  /**
   * Return the input as string.
   *
   * @return string
   *   The input as string.
   */
  public function toString(): string;

  /**
   * Returns all debug data.
   *
   * @return array
   *   The debug data.
   */
  public function getDebugData(): array;

  /**
   * Set the debug data.
   *
   * @param array $debugData
   *   The debug data.
   */
  public function setDebugData(array $debugData): void;

  /**
   * Set one debug data.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function setDebugDataValue(string $key, $value): void;

  /**
   * Set the guardrail set for this input.
   *
   * @param \Drupal\ai\Guardrail\AiGuardrailSetInterface $guardrails
   *   The guardrail set to set.
   *
   * @deprecated in ai:1.4.0 and is removed from ai:2.0.0. Use
   *   ::addGuardrailSet() instead.
   *
   * @see https://www.drupal.org/project/ai/issues/3584849
   */
  public function setGuardrailSet(AiGuardrailSetInterface $guardrails): void;

  /**
   * Get the guardrail set for this input.
   *
   * @return \Drupal\ai\Guardrail\AiGuardrailSetInterface|null
   *   The guardrail set for the chat, or NULL if not set.
   *
   * @deprecated in ai:1.4.0 and is removed from ai:2.0.0. Use
   *   ::getGuardrailSets() instead.
   *
   * @see https://www.drupal.org/project/ai/issues/3584849
   */
  public function getGuardrailSet(): ?AiGuardrailSetInterface;

  /**
   * Add a guardrail set to this input.
   *
   * Multiple sets may be attached; each is evaluated independently by the
   * guardrails event subscriber. Adding a set with an id that is already
   * attached replaces that entry in place.
   *
   * @param \Drupal\ai\Guardrail\AiGuardrailSetInterface $guardrails
   *   The guardrail set to add.
   */
  public function addGuardrailSet(AiGuardrailSetInterface $guardrails): void;

  /**
   * Replace all guardrail sets attached to this input.
   *
   * Iteration order of the resulting array matches the order of the values
   * passed in. Entries are re-keyed internally by set id, so passing either
   * a list (e.g. [$a, $b]) or a keyed map (e.g. ['a' => $a]) works.
   *
   * @param \Drupal\ai\Guardrail\AiGuardrailSetInterface[] $guardrails
   *   The guardrail sets to attach, in the order they should run.
   */
  public function setGuardrailSets(array $guardrails): void;

  /**
   * Get all guardrail sets attached to this input.
   *
   * @return \Drupal\ai\Guardrail\AiGuardrailSetInterface[]
   *   The guardrail sets, keyed by set id, in insertion order.
   */
  public function getGuardrailSets(): array;

  /**
   * Take note of an applied guardrail result.
   *
   * @param \Drupal\ai\Guardrail\Result\GuardrailResultInterface $guardrailResult
   *   An applied guardrail result.
   * @param \Drupal\ai\Entity\AiGuardrailModeEnum $mode
   *   The guardrail mode.
   */
  public function addGuardrailResult(GuardrailResultInterface $guardrailResult, AiGuardrailModeEnum $mode): void;

  /**
   * Get all applied guardrails results.
   *
   * Flat list keyed by mode value; only the last result per mode is returned.
   * Retained for backward compatibility - use ::getAllGuardrailResults() to
   * access every applied result across every guardrail set and mode.
   *
   * @return \Drupal\ai\Guardrail\Result\GuardrailResultInterface[]
   *   The applied guardrails results, keyed by mode value.
   *
   * @deprecated in ai:1.4.0 and is removed from ai:2.0.0. Use
   *   ::getAllGuardrailResults() instead.
   *
   * @see https://www.drupal.org/project/ai/issues/3584849
   */
  public function getGuardrailsResults(): array;

  /**
   * Get every applied guardrail result, grouped by mode.
   *
   * Unlike ::getGuardrailsResults(), this accumulates every result produced
   * by every guardrail in every attached set, so nothing is overwritten when
   * multiple guardrails run under the same mode.
   *
   * @return \Drupal\ai\Guardrail\Result\GuardrailResultInterface[][]
   *   Results grouped by mode value, in insertion order within each mode.
   */
  public function getAllGuardrailResults(): array;

  /**
   * Returns the input as an array.
   *
   * @return array
   *   The input as an array.
   */
  public function toArray(): array;

}
