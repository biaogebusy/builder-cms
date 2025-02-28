<?php

namespace Drupal\entity_print\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

/**
 * Defines the Print Engine specific configuration.
 *
 * @ConfigEntityType(
 *   id = "print_engine",
 *   label = @Translation("Print Engine"),
 *   config_prefix = "print_engine",
 *   entity_keys = {
 *     "id" = "id"
 *   },
 *   admin_permission = "administer entity print",
 *   config_export = {
 *     "id" = "id",
 *     "settings"
 *   }
 * )
 */
class PrintEngineStorage extends ConfigEntityBase implements PrintEngineStorageInterface {

  /**
   * The plugin collection for one Print engine.
   *
   * @var \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection
   */
  protected $printEnginePluginCollection;

  /**
   * An array of plugin settings for this specific Print engine.
   *
   * @var array
   */
  protected $settings = [];

  /**
   * The id of the Print engine plugin.
   *
   * @var string
   */
  protected $id;

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function setSettings(array $settings) {
    $this->settings = $settings;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrintEnginePluginCollection() {
    if (!$this->printEnginePluginCollection) {
      $this->printEnginePluginCollection = new DefaultSingleLazyPluginCollection($this->getPrintEnginePluginManager(), $this->id, $this->settings);
    }
    return $this->printEnginePluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return ['settings' => $this->getPrintEnginePluginCollection()];
  }

  /**
   * Gets the plugin manager.
   *
   * @return \Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface
   *   The plugin manager instance.
   */
  protected function getPrintEnginePluginManager() {
    return \Drupal::service('plugin.manager.entity_print.print_engine');
  }

}
