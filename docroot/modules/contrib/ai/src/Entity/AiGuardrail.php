<?php

declare(strict_types=1);

namespace Drupal\ai\Entity;

use Drupal\ai\Guardrail\AiGuardrailInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ai\Guardrail\AiGuardrailEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

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
final class AiGuardrail extends ConfigEntityBase implements AiGuardrailEntityInterface, EntityWithPluginCollectionInterface {

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
   * The guardrail plugin collection.
   *
   * Not part of config_export. ConfigEntityBase::__sleep() removes it from the
   * serialized payload, which is what keeps the plugin instance — and any
   * service it holds — out of the form cache.
   *
   * @var \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection|null
   */
  protected ?DefaultSingleLazyPluginCollection $pluginCollection = NULL;

  /**
   * Encapsulates the creation of the guardrail's plugin collection.
   *
   * @return \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection|null
   *   The plugin collection, or NULL when no guardrail plugin is selected yet.
   */
  protected function getPluginCollection(): ?DefaultSingleLazyPluginCollection {
    if (!isset($this->guardrail)) {
      return NULL;
    }

    if ($this->pluginCollection === NULL) {
      try {
        $this->pluginCollection = new DefaultSingleLazyPluginCollection(
          \Drupal::service('plugin.manager.ai_guardrail'),
          $this->guardrail,
          $this->guardrail_settings
        );
      }
      catch (PluginException) {
        // The configured plugin is gone: its module was uninstalled or the ID
        // was renamed. Behave as if no guardrail is selected. ConfigEntityBase
        // calls getPluginCollections() from set(), __sleep() and
        // calculateDependencies(), so letting this escape would fatal on every
        // one of them and take the stored settings down with it.
        return NULL;
      }
    }

    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    $collection = $this->getPluginCollection();

    return $collection === NULL ? [] : ['guardrail_settings' => $collection];
  }

  /**
   * Returns the guardrail plugin instance.
   *
   * @return \Drupal\ai\Guardrail\AiGuardrailInterface|null
   *   The guardrail plugin instance, or NULL if not set.
   */
  public function getGuardrail(): ?AiGuardrailInterface {
    $collection = $this->getPluginCollection();
    if ($collection === NULL) {
      return NULL;
    }

    try {
      // Passing the current plugin ID rather than relying on the collection's
      // own instance ID means a guardrail that was swapped out by
      // self::set() is instantiated fresh instead of handing back the
      // previously selected one.
      $plugin = $collection->get($this->guardrail);
    }
    catch (PluginException) {
      return NULL;
    }

    return $plugin instanceof AiGuardrailInterface ? $plugin : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value) {
    // The guardrail form writes the newly selected plugin ID onto the entity
    // on every AJAX rebuild. Drop the collection so it is rebuilt for the new
    // selection, otherwise the settings subform stays on the old guardrail.
    // Both assignments happen before the parent call because
    // ConfigEntityBase::set() itself calls getPluginCollections(), which would
    // otherwise rebuild the collection on the plugin ID we are replacing.
    if ($property_name === 'guardrail' && $value !== ($this->guardrail ?? NULL)) {
      $this->pluginCollection = NULL;
      $this->guardrail = $value;
    }

    return parent::set($property_name, $value);
  }

  /**
   * Sets the guardrail plugin ID.
   */
  public function setPlugin($id): void {
    $this->set('guardrail', $id);
  }

  /**
   * Returns the guardrail settings.
   *
   * @return array
   *   The guardrail settings.
   */
  public function getSettings(): array {
    return $this->getPluginCollection()?->getConfiguration() ?? $this->guardrail_settings;
  }

}
