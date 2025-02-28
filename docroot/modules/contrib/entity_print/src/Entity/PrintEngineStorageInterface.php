<?php

namespace Drupal\entity_print\Entity;

use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * An interface for our config entity storage for Print engines.
 */
interface PrintEngineStorageInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Gets a single lazy plugin collection.
   *
   * @return \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection
   *   The plugin collection for our Print Engine plugin.
   */
  public function getPrintEnginePluginCollection();

  /**
   * Gets the Print engine settings.
   *
   * @return array
   *   The Print Engine settings.
   */
  public function getSettings();

  /**
   * Sets the Print engine settings.
   *
   * @return $this
   *   The config entity.
   */
  public function setSettings(array $settings);

}
