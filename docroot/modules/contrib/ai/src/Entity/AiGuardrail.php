<?php

declare(strict_types=1);

namespace Drupal\ai\Entity;

use Drupal\ai\Guardrail\AiGuardrailInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ai\Guardrail\AiGuardrailEntityInterface;

/**
 * Defines the guardrail entity type.
 *
 * @ConfigEntityType(
 *   id = "ai_guardrail",
 *   label = @Translation("AI Guardrail"),
 *   label_collection = @Translation("AI Guardrails"),
 *   label_singular = @Translation("AI Guardrail"),
 *   label_plural = @Translation("AI Guardrails"),
 *   label_count = @PluralTranslation(
 *     singular = "@count guardrail",
 *     plural = "@count guardrails",
 *   ),
 *   config_prefix = "ai_guardrail",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *    },
 *   handlers = {
 *     "list_builder" = "Drupal\ai\AiGuardrailListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai\Form\AiGuardrailForm",
 *       "edit" = "Drupal\ai\Form\AiGuardrailForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   links = {
 *     "collection" = "/admin/config/ai/guardrails",
 *     "add-form" = "/admin/config/ai/guardrails/add",
 *     "edit-form" = "/admin/config/ai/guardrails/{ai_guardrail}",
 *     "delete-form" = "/admin/config/ai/guardrails/{ai_guardrail}/delete",
 *   },
 *   admin_permission = "administer guardrails",
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "guardrail",
 *     "guardrail_settings"
 *   }
 * )
 */
final class AiGuardrail extends ConfigEntityBase implements AiGuardrailEntityInterface {

  /**
   * The guardrail ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * The guardrail label.
   *
   * @var string
   */
  protected string $label;

  /**
   * The guardrail description.
   *
   * @var string
   */
  protected string $description;

  /**
   * The guardrail plugin ID.
   *
   * @var string
   */
  protected string $guardrail;

  /**
   * The guardrail settings.
   *
   * @var array
   */
  protected array $guardrail_settings = [];

  /**
   * The guardrail plugin instance.
   *
   * @var \Drupal\ai\Guardrail\AiGuardrailInterface|null
   */
  private ?AiGuardrailInterface $guardrail_plugin = NULL;

  /**
   * Returns the guardrail plugin instance.
   *
   * @return \Drupal\ai\Guardrail\AiGuardrailInterface|null
   *   The guardrail plugin instance, or NULL if not set.
   */
  public function getGuardrail(): ?AiGuardrailInterface {
    if (isset($this->guardrail_plugin)) {
      return $this->guardrail_plugin;
    }

    if (!isset($this->guardrail)) {
      return NULL;
    }

    /** @var \Drupal\ai\Guardrail\AiGuardrailPluginManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.ai_guardrail');
    try {
      $this->guardrail_plugin = $plugin_manager->createInstance(
        $this->guardrail,
        $this->guardrail_settings
      );
    }
    catch (PluginException $e) {
      return NULL;
    }

    return $this->guardrail_plugin;
  }

  /**
   * Sets the guardrail plugin ID.
   */
  public function setPlugin($id): void {
    $this->guardrail = $id;
  }

  /**
   * Returns the guardrail settings.
   *
   * @return array
   *   The guardrail settings.
   */
  public function getSettings(): array {
    return $this->guardrail_settings;
  }

}
