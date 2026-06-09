<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\ai_automators\AiAutomatorInterface;

/**
 * Defines the ai automator entity type.
 *
 * @ConfigEntityType(
 *   id = "ai_automator",
 *   label = @Translation("AI Automator"),
 *   label_collection = @Translation("AI Automators"),
 *   label_singular = @Translation("ai automator"),
 *   label_plural = @Translation("ai automators"),
 *   label_count = @PluralTranslation(
 *     singular = "@count ai automator",
 *     plural = "@count ai automators",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_automators\AiAutomatorListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai_automators\Form\AiAutomatorForm",
 *       "edit" = "Drupal\ai_automators\Form\AiAutomatorForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "ai_automator",
 *   admin_permission = "administer ai_automator",
 *   links = {
 *     "collection" = "/admin/config/ai/ai-automators/ai-automator",
 *     "add-form" = "/admin/config/ai/ai-automators/ai-automator/add",
 *     "edit-form" = "/admin/config/ai/ai-automators/ai-automator/{ai_automator}",
 *     "delete-form" = "/admin/config/ai/ai-automators/ai-automator/{ai_automator}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "rule",
 *     "input_mode",
 *     "weight",
 *     "worker_type",
 *     "entity_type",
 *     "bundle",
 *     "field_name",
 *     "edit_mode",
 *     "base_field",
 *     "prompt",
 *     "token",
 *     "guardrail_set_id",
 *     "plugin_config",
 *   },
 * )
 */
final class AiAutomator extends ConfigEntityBase implements AiAutomatorInterface {

  /**
   * The example ID.
   */
  protected string $id;

  /**
   * The example label.
   */
  protected string $label;

  /**
   * The AI Automator rule type.
   */
  protected string $rule;

  /**
   * The AI Automator input mode.
   */
  protected string $input_mode;

  /**
   * The AI Automator weight.
   */
  protected int $weight;

  /**
   * The AI Automator worker type.
   */
  protected string $worker_type;

  /**
   * The AI Automator entity type.
   */
  protected string $entity_type;

  /**
   * The AI Automator bundle.
   */
  protected string $bundle;

  /**
   * The AI Automator field name.
   */
  protected string $field_name;

  /**
   * The AI Automator edit mode.
   */
  protected bool $edit_mode;

  /**
   * The AI Automator base field.
   */
  protected string|null $base_field;

  /**
   * The AI Automator prompt.
   */
  protected string|null $prompt;

  /**
   * The AI Automator token.
   */
  protected string|null $token;

  /**
   * The guardrail set applied to this Automator's AI calls.
   */
  protected string|null $guardrail_set_id = NULL;

  /**
   * The plugin config.
   */
  protected array $plugin_config;

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);
    \Drupal::service('plugin.manager.action')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities): void {
    parent::postDelete($storage, $entities);
    \Drupal::service('plugin.manager.action')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    // Set the dependencies its connected to.
    $this->addDependency('config', 'field.field.' . $this->entity_type . '.' . $this->bundle . '.' . $this->field_name);
    if (!empty($this->guardrail_set_id)) {
      $guardrail_set = \Drupal::entityTypeManager()
        ->getStorage('ai_guardrail_set')
        ->load($this->guardrail_set_id);
      if ($guardrail_set) {
        $this->addDependency($guardrail_set->getConfigDependencyKey(), $guardrail_set->getConfigDependencyName());
      }
    }
    return $dependencies;
  }

}
