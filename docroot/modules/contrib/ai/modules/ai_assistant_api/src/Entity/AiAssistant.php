<?php

declare(strict_types=1);

namespace Drupal\ai_assistant_api\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ai_assistant_api\AiAssistantInterface;

/**
 * Defines the AI assistant entity type.
 *
 * @ConfigEntityType(
 *   id = "ai_assistant",
 *   label = @Translation("AI Assistant"),
 *   label_collection = @Translation("AI Assistants"),
 *   label_singular = @Translation("AI Assistant"),
 *   label_plural = @Translation("AI Assistants"),
 *   label_count = @PluralTranslation(
 *     singular = "@count AI assistant",
 *     plural = "@count AI Assistants",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_assistant_api\AiAssistantListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai_assistant_api\Form\AiAssistantForm",
 *       "edit" = "Drupal\ai_assistant_api\Form\AiAssistantForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "ai_assistant",
 *   admin_permission = "administer ai_assistant",
 *   links = {
 *     "collection" = "/admin/config/ai/ai-assistant",
 *     "add-form" = "/admin/config/ai/ai-assistant/add",
 *     "edit-form" = "//admin/config/ai/ai-assistant/{ai_assistant}",
 *     "delete-form" = "/admin/config/ai/ai-assistant/{ai_assistant}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "allow_history",
 *     "history_context_length",
 *     "pre_action_prompt",
 *     "system_prompt",
 *     "instructions",
 *     "actions_enabled",
 *     "error_message",
 *     "specific_error_messages",
 *     "llm_provider",
 *     "llm_model",
 *     "llm_configuration",
 *     "roles",
 *     "use_function_calling",
 *     "ai_agent",
 *   },
 * )
 */
final class AiAssistant extends ConfigEntityBase implements AiAssistantInterface {

  /**
   * The example ID.
   */
  protected string $id;

  /**
   * The example label.
   */
  protected string $label;

  /**
   * The example description.
   */
  protected string $description;

  /**
   * Allow history.
   */
  protected string $allow_history;

  /**
   * History context length.
   */
  protected string $history_context_length = "2";

  /**
   * The system role.
   */
  protected string $system_role;

  /**
   * The pre action prompt.
   */
  protected ?string $pre_action_prompt;

  /**
   * The system prompt.
   *
   * @var string
   */
  protected ?string $system_prompt;

  /**
   * The instructions for the LLM.
   */
  protected ?string $instructions;

  /**
   * The instructions for the pre action prompt.
   */
  protected string $preprompt_instructions;

  /**
   * The actions enabled and their config.
   */
  protected array $actions_enabled = [];

  /**
   * The assistant message.
   */
  protected string $assistant_message;

  /**
   * The generic error message.
   */
  protected string $error_message;

  /**
   * The specific error message overrides.
   */
  protected ?array $specific_error_messages;

  /**
   * The LLM provider.
   */
  protected string $llm_provider;

  /**
   * The LLM model.
   */
  protected string $llm_model;

  /**
   * The LLM configuration.
   */
  protected array $llm_configuration;

  /**
   * The roles that can run this assistant.
   */
  protected array $roles = [];

  /**
   * Use function calling.
   */
  protected ?bool $use_function_calling = FALSE;

  /**
   * An AI Agent.
   */
  protected ?string $ai_agent = NULL;

}
