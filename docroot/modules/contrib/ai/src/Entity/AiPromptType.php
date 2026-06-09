<?php

namespace Drupal\ai\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the AI Prompt Type config entity.
 *
 * @ConfigEntityType(
 *   id = "ai_prompt_type",
 *   label = @Translation("AI Prompt type"),
 *   label_collection = @Translation("AI Prompt types"),
 *   label_singular = @Translation("AI Prompt type"),
 *   label_plural = @Translation("AI Prompt types"),
 *   config_prefix = "ai_prompt_type",
 *   bundle_of = "ai_prompt",
 *   entity_keys = {
 *     "id" = "id"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\ai\AiPromptTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai\Form\AiPromptTypeForm",
 *       "edit" = "Drupal\ai\Form\AiPromptTypeForm",
 *       "delete" = "Drupal\ai\Form\AiPromptTypeDeleteForm"
 *     }
 *   },
 *   links = {
 *     "delete-form" = "/admin/config/ai/prompts/prompt-types/{ai_prompt_type}/delete",
 *     "edit-form" = "/admin/config/ai/prompts/prompt-types/{ai_prompt_type}",
 *     "collection" = "/admin/config/ai/prompts/prompt-types"
 *   },
 *   admin_permission = "administer ai prompt types",
 *   label_count = @PluralTranslation(
 *     singular = "@count AI Prompt Type",
 *     plural = "@count AI Prompt Types",
 *   ),
 *   config_export = {
 *     "id",
 *     "label",
 *     "variables",
 *     "tokens"
 *   }
 * )
 */
class AiPromptType extends ConfigEntityBundleBase implements AiPromptTypeInterface {

  /**
   * The AI Prompt Type ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * The AI Prompt Type label.
   *
   * @var string
   */
  protected string $label;

  /**
   * The AI Prompt Type required and suggested variables.
   *
   * @var array
   */
  protected array $variables = [];

  /**
   * The AI Prompt Type required and suggested tokens.
   *
   * @var array
   */
  protected array $tokens = [];

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    if (isset($this->label)) {
      return $this->label;
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getVariables(): array {
    return $this->variables;
  }

  /**
   * {@inheritdoc}
   */
  public function getTokens(): array {
    return $this->tokens;
  }

  /**
   * {@inheritdoc}
   */
  public function getPromptCount(): int {
    $query = $this->entityTypeManager()->getStorage('ai_prompt')->getQuery();
    $query->condition('type', $this->id());
    $query->accessCheck(FALSE);
    return $query->count()->execute() ?? 0;
  }

}
