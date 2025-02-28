<?php

namespace Drupal\commerce_tax\Entity;

use Drupal\commerce\CommerceSinglePluginCollection;
use Drupal\commerce\ConditionGroup;
use Drupal\commerce\Plugin\Commerce\Condition\ParentEntityAwareInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the tax type entity class.
 *
 * @ConfigEntityType(
 *   id = "commerce_tax_type",
 *   label = @Translation("Tax type"),
 *   label_collection = @Translation("Tax types"),
 *   label_singular = @Translation("tax type"),
 *   label_plural = @Translation("tax types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count tax type",
 *     plural = "@count tax types",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\commerce_tax\TaxTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\commerce_tax\Form\TaxTypeForm",
 *       "edit" = "Drupal\commerce_tax\Form\TaxTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "commerce_tax_type",
 *   admin_permission = "administer commerce_tax_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "plugin",
 *     "configuration",
 *     "conditions",
 *     "conditionOperator",
 *   },
 *   links = {
 *     "add-form" = "/admin/commerce/config/tax-types/add",
 *     "edit-form" = "/admin/commerce/config/tax-types/manage/{commerce_tax_type}",
 *     "delete-form" = "/admin/commerce/config/tax-types/manage/{commerce_tax_type}/delete",
 *     "collection" =  "/admin/commerce/config/tax-types"
 *   },
 *   static_cache = true
 * )
 */
class TaxType extends ConfigEntityBase implements TaxTypeInterface {

  /**
   * The tax type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The tax type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The plugin ID.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The plugin configuration.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * The conditions.
   *
   * @var array
   */
  protected $conditions = [];

  /**
   * The condition operator.
   *
   * @var string
   */
  protected $conditionOperator = 'AND';

  /**
   * The plugin collection that holds the tax type plugin.
   *
   * @var \Drupal\commerce\CommerceSinglePluginCollection
   */
  protected $pluginCollection;

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    return $this->getPluginCollection()->get($this->plugin);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginId($plugin_id) {
    $this->plugin = $plugin_id;
    $this->configuration = [];
    $this->pluginCollection = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginConfiguration(array $configuration) {
    $this->configuration = $configuration;
    $this->pluginCollection = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConditions() {
    /** @var \Drupal\commerce\ConditionManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.commerce_condition');
    $conditions = [];
    foreach ($this->conditions as $condition) {
      if (empty($condition['plugin'])) {
        continue;
      }
      $condition = $plugin_manager->createInstance($condition['plugin'], (array) $condition['configuration']);
      if ($condition instanceof ParentEntityAwareInterface) {
        $condition->setParentEntity($this);
      }
      $conditions[] = $condition;
    }
    return $conditions;
  }

  /**
   * {@inheritdoc}
   */
  public function getConditionOperator() {
    return $this->conditionOperator;
  }

  /**
   * {@inheritdoc}
   */
  public function setConditionOperator($condition_operator) {
    $this->conditionOperator = $condition_operator;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(OrderInterface $order) {
    $conditions = $this->getConditions();
    if (!$conditions) {
      return TRUE;
    }
    $order_conditions = array_filter($conditions, function ($condition) {
      /** @var \Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface $condition */
      return $condition->getEntityTypeId() == 'commerce_order';
    });
    $order_conditions = new ConditionGroup($order_conditions, $this->getConditionOperator());

    return $order_conditions->evaluate($order);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return [
      'configuration' => $this->getPluginCollection(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value) {
    // Invoke the setters to clear related properties.
    if ($property_name == 'plugin') {
      $this->setPluginId($value);
    }
    elseif ($property_name == 'configuration') {
      $this->setPluginConfiguration($value);
    }
    else {
      return parent::set($property_name, $value);
    }
  }

  /**
   * Gets the plugin collection that holds the tax type plugin.
   *
   * Ensures the plugin collection is initialized before returning it.
   *
   * @return \Drupal\commerce\CommerceSinglePluginCollection
   *   The plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      $plugin_manager = \Drupal::service('plugin.manager.commerce_tax_type');
      $this->pluginCollection = new CommerceSinglePluginCollection($plugin_manager, $this->plugin, $this->configuration, $this);
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    /** @var \Drupal\commerce_tax\Entity\TaxTypeInterface $a */
    /** @var \Drupal\commerce_tax\Entity\TaxTypeInterface $b */
    $a_plugin = $a->getPlugin();
    $b_plugin = $b->getPlugin();
    $a_weight = $a_plugin ? $a_plugin->getWeight() : 0;
    $b_weight = $b_plugin ? $b_plugin->getWeight() : 0;
    if ($a_weight == $b_weight) {
      $a_label = $a->label();
      $b_label = $b->label();
      return strnatcasecmp($a_label, $b_label);
    }
    return ($a_weight < $b_weight) ? -1 : 1;
  }

}
