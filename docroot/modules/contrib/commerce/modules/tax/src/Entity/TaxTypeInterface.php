<?php

namespace Drupal\commerce_tax\Entity;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Defines the interface for tax types.
 *
 * This configuration entity stores configuration for tax type plugins.
 */
interface TaxTypeInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Gets the tax type plugin.
   *
   * @return \Drupal\commerce_tax\Plugin\Commerce\TaxType\TaxTypeInterface
   *   The tax type plugin.
   */
  public function getPlugin();

  /**
   * Gets the tax type plugin ID.
   *
   * @return string
   *   The tax type plugin ID.
   */
  public function getPluginId();

  /**
   * Sets the tax type plugin ID.
   *
   * @param string $plugin_id
   *   The tax type plugin ID.
   *
   * @return $this
   */
  public function setPluginId($plugin_id);

  /**
   * Gets the tax type plugin configuration.
   *
   * @return array
   *   The tax type plugin configuration.
   */
  public function getPluginConfiguration();

  /**
   * Sets the tax type plugin configuration.
   *
   * @param array $configuration
   *   The tax type plugin configuration.
   *
   * @return $this
   */
  public function setPluginConfiguration(array $configuration);

  /**
   * Gets the conditions.
   *
   * @return \Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface[]
   *   The conditions.
   */
  public function getConditions();

  /**
   * Gets the tax type condition operator.
   *
   * @return string
   *   The condition operator. Possible values: AND, OR.
   */
  public function getConditionOperator();

  /**
   * Sets the tax type condition operator.
   *
   * @param string $condition_operator
   *   The condition operator.
   *
   * @return $this
   */
  public function setConditionOperator($condition_operator);

  /**
   * Checks whether the tax type applies to the given order.
   *
   * Ensures that the conditions pass.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return bool
   *   TRUE if the tax type applies, FALSE otherwise.
   */
  public function applies(OrderInterface $order);

}
