<?php

namespace Drupal\ai\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the AI Prompt config entity.
 *
 * @ConfigEntityType(
 *   id = "ai_prompt",
 *   module = "ai",
 *   label = @Translation("AI Prompt"),
 *   label_collection = @Translation("AI Prompts"),
 *   label_singular = @Translation("AI Prompt"),
 *   label_plural = @Translation("AI Prompts"),
 *   config_prefix = "ai_prompt",
 *   entity_keys = {
 *     "id" = "id"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\ai\AiPromptListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai\Form\AiPromptForm",
 *       "edit" = "Drupal\ai\Form\AiPromptForm",
 *       "delete" = "Drupal\ai\Form\AiPromptDeleteForm"
 *     }
 *   },
 *   links = {
 *     "add-form" = "/admin/config/ai/prompts/add",
 *     "delete-form" = "/admin/config/ai/prompts/{ai_prompt}/delete",
 *     "edit-form" = "/admin/config/ai/prompts/{ai_prompt}",
 *     "collection" = "/admin/config/ai/prompts"
 *   },
 *   admin_permission = "manage ai prompts",
 *   label_count = {
 *     "singular" = "@count AI Prompt",
 *     "plural" = "@count AI Prompts"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "type",
 *     "prompt"
 *   }
 * )
 */
class AiPrompt extends ConfigEntityBase implements AiPromptInterface {

  /**
   * The AI Prompt ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * The AI Prompt label.
   *
   * @var string
   */
  protected string $label;

  /**
   * The AI Prompt Type bundle ID.
   *
   * @var string
   */
  protected string $type;

  /**
   * The AI Prompt Type entity.
   *
   * @var \Drupal\ai\Entity\AiPromptTypeInterface
   */
  protected AiPromptTypeInterface $promptType;

  /**
   * The AI Prompt text.
   *
   * @var string
   */
  protected string $prompt;

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function bundle(): string {
    return $this->type ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getPrompt(): string {
    return $this->prompt ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setPrompt($prompt): void {
    $this->prompt = $prompt;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): AiPromptTypeInterface {
    if (isset($this->promptType)) {
      return $this->promptType;
    }
    $this->promptType = AiPromptType::load($this->type);
    return $this->promptType;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeVariables(): array {
    return $this->getType()->getVariables();
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeTokens(): array {
    return $this->getType()->getTokens();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Ensure the bundle property is set.
    if (empty($this->bundle())) {
      throw new \LogicException('AI Prompt bundle property is not set before saving.');
    }

    // Strip existing bundle and rebuild the ID to prefix with the new bundle.
    $id = $this->id();
    if (str_contains($id, '__')) {
      $id_parts = explode('__', $id);
      $id = end($id_parts);
    }

    // This makes it so that the prompt entities are stored in configuration
    // like ai.ai_prompt.prompt_type_here__prompt_id_here.yml. This is helpful
    // for module developers to take advantage of Config Ignore wildcards if
    // they want their editors to be able to manage the particular prompt type
    // as content rather than configuration. See ai_prompt_management.md for
    // more information.
    $this->id = $this->bundle() . '__' . $id;
  }

}
