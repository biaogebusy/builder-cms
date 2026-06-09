<?php

declare(strict_types=1);

namespace Drupal\ai\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ai\Guardrail\AiGuardrailSetInterface;

/**
 * Defines the guardrail set entity type.
 *
 * @ConfigEntityType(
 *   id = "ai_guardrail_set",
 *   label = @Translation("AI Guardrail set"),
 *   label_collection = @Translation("AI Guardrail sets"),
 *   label_singular = @Translation("AI Guardrail set"),
 *   label_plural = @Translation("AI Guardrail sets"),
 *   label_count = @PluralTranslation(
 *     singular = "@count guardrail set",
 *     plural = "@count guardrail sets",
 *   ),
 *   config_prefix = "ai_guardrail_set",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *    },
 *   handlers = {
 *     "list_builder" = "Drupal\ai\AiGuardrailSetListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai\Form\AiGuardrailSetForm",
 *       "edit" = "Drupal\ai\Form\AiGuardrailSetForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   links = {
 *     "collection" = "/admin/config/ai/guardrails/guardrail-sets",
 *     "add-form" = "/admin/config/ai/guardrails/guardrail_sets/add",
 *     "edit-form" = "/admin/config/ai/guardrails/guardrail_sets/{ai_guardrail_set}",
 *     "delete-form" = "/admin/config/ai/guardrails/guardrail_sets/{ai_guardrail_set}/delete",
 *   },
 *   admin_permission = "administer guardrail sets",
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "stop_threshold",
 *     "pre_generate_guardrails",
 *     "post_generate_guardrails"
 *   }
 * )
 */
final class AiGuardrailSet extends ConfigEntityBase implements AiGuardrailSetInterface {

  /**
   * The guardrail set ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * The guardrail set label.
   *
   * @var string
   */
  protected string $label;

  /**
   * The guardrail set description.
   *
   * @var string
   */
  protected string $description;

  /**
   * After this threshold is reached, the generation will be stopped.
   *
   * @var float
   */
  protected float $stop_threshold;

  /**
   * The guardrails to execute before the generation.
   */
  protected array $pre_generate_guardrails = [];

  /**
   * The guardrails to execute after the generation.
   */
  protected array $post_generate_guardrails = [];

  /**
   * {@inheritdoc}
   */
  public function getPreGenerateGuardrails(): array {
    $guardrails = [];

    foreach ($this->pre_generate_guardrails['plugin_id'] as $pre_generate_guardrail) {
      $guardrail_entity = AiGuardrail::load($pre_generate_guardrail);
      $guardrails[] = $guardrail_entity->getGuardrail();
    }

    return $guardrails;
  }

  /**
   * {@inheritdoc}
   */
  public function getPostGenerateGuardrails(): array {
    $guardrails = [];
    foreach ($this->post_generate_guardrails['plugin_id'] as $post_generate_guardrail) {
      $guardrail_entity = AiGuardrail::load($post_generate_guardrail);
      $guardrails[] = $guardrail_entity->getGuardrail();
    }

    return $guardrails;
  }

  /**
   * {@inheritdoc}
   */
  public function getStopThreshold(): float {
    return $this->stop_threshold;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): static {
    parent::calculateDependencies();
    // Collect all used guardrail plugin ids.
    $guardrail_ids = array_merge(
      $this->pre_generate_guardrails['plugin_id'] ?? [],
      $this->post_generate_guardrails['plugin_id'] ?? [],
    );
    // Create unique list of plugin ids.
    $guardrail_ids = array_unique($guardrail_ids);
    if (!empty($guardrail_ids)) {
      // In case there are guardrails, add them as config dependencies.
      foreach (AiGuardrail::loadMultiple($guardrail_ids) as $guardrail) {
        $this->addDependency('config', $guardrail->getConfigDependencyName());
      }
    }
    return $this;
  }

}
