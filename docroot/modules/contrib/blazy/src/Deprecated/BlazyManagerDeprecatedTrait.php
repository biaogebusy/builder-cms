<?php

namespace Drupal\blazy\Deprecated;

use Drupal\blazy\Media\BlazyResponsiveImage;

/**
 * Deprecated in blazy:8.x-2.16.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 *
 * @todo deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use
 *   \Drupal\blazy\BlazyInterface methods instead.
 */
trait BlazyManagerDeprecatedTrait {

  /**
   * Deprecated method to return the entity repository service.
   *
   * @todo deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use
   *   BlazyInterface::entityRepository() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function getEntityRepository() {
    @trigger_error('getEntityRepository is deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::entityRepository() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return $this->entityRepository;
  }

  /**
   * Deprecated method to return the entity type manager.
   *
   * @todo deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use
   *   BlazyInterface::entityTypeManager() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function getEntityTypeManager() {
    @trigger_error('getEntityTypeManager is deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::entityTypeManager() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return $this->entityTypeManager;
  }

  /**
   * Deprecated method to return the module handler.
   *
   * @todo deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use
   *   BlazyInterface::moduleHandler() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function getModuleHandler() {
    @trigger_error('getModuleHandler is deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::moduleHandler() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return $this->moduleHandler;
  }

  /**
   * Deprecated method to return the renderer.
   *
   * @todo deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use
   *   BlazyInterface::renderer() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function getRenderer() {
    @trigger_error('getRenderer is deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::renderer() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return $this->renderer;
  }

  /**
   * Deprecated method to return the config factory.
   *
   * @todo deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use
   *   BlazyInterface::configFactory() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function getConfigFactory() {
    @trigger_error('getConfigFactory is deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::configFactory() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return $this->configFactory;
  }

  /**
   * Deprecated method to return the cache.
   *
   * @todo deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use
   *   BlazyInterface::cache() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function getCache() {
    @trigger_error('getCache is deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::cache() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return $this->cache;
  }

  /**
   * Deprecated method to return any config, or keyed by the $setting_name.
   *
   * @todo deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use
   *   BlazyInterface::config() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function configLoad($setting_name = '', $settings = 'blazy.settings') {
    @trigger_error('configLoad is deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::config() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return $this->config($setting_name, $settings);
  }

  /**
   * Deprecated method to return a config entity: image_style, slick, etc.
   *
   * @todo deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use
   *   BlazyInterface::load() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function entityLoad($id, $type = 'image_style') {
    @trigger_error('entityLoad is deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::load() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return $this->load($id, $type);
  }

  /**
   * Deprecated method to return multiple configuration entities.
   *
   * @todo deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use
   *   BlazyInterface::loadMultiple() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function entityLoadMultiple($type = 'image_style', $ids = NULL) {
    @trigger_error('entityLoadMultiple is deprecated in blazy:8.x-2.16 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::loadMultiple() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return $this->loadMultiple($type, $ids);
  }

  /**
   * Deprecated method to return skins via hook_MODULE_NAME_skins_info().
   *
   * @todo remove for sub-modules own skins as plugins at blazy:8.x-2.1+.
   * @see https://www.drupal.org/node/2233261
   * @see https://www.drupal.org/node/3105670
   */
  public function buildSkins($namespace, $skin_class, $methods = []) {
    @trigger_error('buildSkins is deprecated in blazy:8.x-2.1 and is removed from blazy:3.0.0. Use sub-module skin plugins instead. See https://www.drupal.org/node/2233261', E_USER_DEPRECATED);
    return [];
  }

  /**
   * Deprecated method, not safe to remove before 3.x for being generic.
   *
   * @todo deprecated in blazy:8.x-2.5 and is removed from blazy:3.0.0. Use
   *   BlazyResponsiveImage::styles() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getResponsiveImageStyles($responsive) {
    @trigger_error('getResponsiveImageStyles is deprecated in blazy:8.x-2.5 and is removed from blazy:3.0.0. Use \Drupal\blazy\Media\BlazyResponsiveImage::styles() instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);
    return BlazyResponsiveImage::styles($responsive);
  }

}
