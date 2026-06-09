<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Repository for AI Guardrail entities.
 */
class AiGuardrailRepository {

  /**
   * Constructs a new AiGuardrailRepository object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entity_type_manager,
  ) {
  }

  /**
   * Loads a guardrail by its ID.
   *
   * @param string $id
   *   The guardrail ID.
   *
   * @return \Drupal\ai\Guardrail\AiGuardrailInterface|null
   *   The guardrail entity or null if not found.
   */
  public function getGuardrailById(string $id): ?AiGuardrailInterface {
    try {
      /** @var \Drupal\ai\Guardrail\AiGuardrailInterface|null $guardrail */
      $guardrail = $this->entity_type_manager
        ->getStorage('ai_guardrail')
        ->load($id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      return NULL;
    }

    return $guardrail;
  }

  /**
   * Loads all guardrails.
   *
   * @return \Drupal\ai\Guardrail\AiGuardrailInterface[]
   *   An array of guardrail entities.
   */
  public function getAllGuardrails(): array {
    try {
      /** @var \Drupal\ai\Guardrail\AiGuardrailInterface[] $guardrails */
      $guardrails = $this->entity_type_manager
        ->getStorage('ai_guardrail')
        ->loadMultiple();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      return [];
    }

    return $guardrails;
  }

  /**
   * Loads a guardrail set by its ID.
   *
   * @param string $id
   *   The guardrail set ID.
   *
   * @return \Drupal\ai\Guardrail\AiGuardrailSetInterface|null
   *   The guardrail set entity or null if not found.
   */
  public function getGuardrailSetById(string $id): ?AiGuardrailSetInterface {
    try {
      /** @var \Drupal\ai\Guardrail\AiGuardrailSetInterface|null $guardrail */
      $guardrail = $this->entity_type_manager
        ->getStorage('ai_guardrail_set')
        ->load($id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      return NULL;
    }

    return $guardrail;
  }

  /**
   * Loads all guardrail sets.
   *
   * @return \Drupal\ai\Guardrail\AiGuardrailSetInterface[]
   *   An array of guardrail set entities.
   */
  public function getAllGuardrailSets(): array {
    try {
      /** @var \Drupal\ai\Guardrail\AiGuardrailSetInterface[] $guardrails */
      $guardrails = $this->entity_type_manager
        ->getStorage('ai_guardrail_set')
        ->loadMultiple();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      return [];
    }

    return $guardrails;
  }

}
