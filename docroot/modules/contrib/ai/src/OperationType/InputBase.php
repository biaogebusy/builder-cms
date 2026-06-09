<?php

namespace Drupal\ai\OperationType;

use Drupal\ai\Entity\AiGuardrailModeEnum;
use Drupal\ai\Guardrail\AiGuardrailSetInterface;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;

/**
 * Base Input Interface class.
 */
abstract class InputBase implements InputInterface {

  /**
   * The debug data.
   *
   * @var array
   */
  private array $debugData = [];

  /**
   * The guardrail sets that will be applied to this input.
   *
   * Keyed by guardrail set id, insertion-ordered.
   *
   * @var \Drupal\ai\Guardrail\AiGuardrailSetInterface[]
   */
  private array $guardrailSets = [];

  /**
   * {@inheritdoc}
   */
  public function getDebugData(): array {
    return $this->debugData;
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugData(array $debug_data): void {
    $this->debugData = $debug_data;
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugDataValue(string $key, $value): void {
    $this->debugData[$key] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setGuardrailSet(AiGuardrailSetInterface $guardrails): void {
    @\trigger_error(
      __METHOD__ . '() is deprecated in ai:1.4.0 and is removed from ai:2.0.0. Use ::addGuardrailSet() instead. See https://www.drupal.org/project/ai/issues/3584849',
      \E_USER_DEPRECATED
    );
    $this->setGuardrailSets([$guardrails]);
  }

  /**
   * {@inheritdoc}
   */
  public function getGuardrailSet(): ?AiGuardrailSetInterface {
    @\trigger_error(
      __METHOD__ . '() is deprecated in ai:1.4.0 and is removed from ai:2.0.0. Use ::getGuardrailSets() instead. See https://www.drupal.org/project/ai/issues/3584849',
      \E_USER_DEPRECATED
    );
    if ($this->guardrailSets === []) {
      return NULL;
    }
    return \reset($this->guardrailSets);
  }

  /**
   * {@inheritdoc}
   */
  public function addGuardrailSet(AiGuardrailSetInterface $guardrails): void {
    $this->guardrailSets[$guardrails->id()] = $guardrails;
  }

  /**
   * {@inheritdoc}
   */
  public function setGuardrailSets(array $guardrails): void {
    $this->guardrailSets = [];
    foreach ($guardrails as $set) {
      // Re-key by id so callers can pass either a list or a keyed map.
      $this->guardrailSets[$set->id()] = $set;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGuardrailSets(): array {
    return $this->guardrailSets;
  }

  /**
   * {@inheritdoc}
   */
  public function addGuardrailResult(GuardrailResultInterface $guardrailResult, AiGuardrailModeEnum $mode): void {
    $applied_guardrails = $this->getDebugData()['applied_guardrails'] ?? [];
    $applied_guardrails[$mode->value] = $guardrailResult;
    $this->setDebugDataValue('applied_guardrails', $applied_guardrails);

    $all_guardrail_results = $this->getDebugData()['all_guardrail_results'] ?? [];
    $all_guardrail_results[$mode->value][] = $guardrailResult;
    $this->setDebugDataValue('all_guardrail_results', $all_guardrail_results);
  }

  /**
   * {@inheritdoc}
   */
  public function getGuardrailsResults(): array {
    @\trigger_error(
      __METHOD__ . '() is deprecated in ai:1.4.0 and is removed from ai:2.0.0. Use ::getAllGuardrailResults() instead. See https://www.drupal.org/project/ai/issues/3584849',
      \E_USER_DEPRECATED
    );
    return $this->getDebugData()['applied_guardrails'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllGuardrailResults(): array {
    return $this->getDebugData()['all_guardrail_results'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'debug_data' => $this->getDebugData(),
    ];
  }

}
