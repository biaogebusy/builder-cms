<?php

namespace Drupal\jsonapi_extras\ResourceType;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager;

/**
 * Defines a configurable resource type.
 */
class ConfigurableResourceType extends ResourceType {

  use DependencySerializationTrait;

  /**
   * The JsonapiResourceConfig entity.
   *
   * @var \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig
   */
  protected $jsonapiResourceConfig;

  /**
   * Plugin manager for enhancers.
   *
   * @var \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager
   */
  protected $enhancerManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The static cache.
   *
   * @var array
   */
  protected $cache = [];

  /**
   * Returns the jsonapi_resource_config.
   *
   * @return \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig
   *   The jsonapi_resource_config entity.
   */
  public function getJsonapiResourceConfig() {
    return $this->jsonapiResourceConfig;
  }

  /**
   * Sets the jsonapi_resource_config.
   *
   * @param \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig $resource_config
   *   The jsonapi_resource_config entity.
   */
  public function setJsonapiResourceConfig(JsonapiResourceConfig $resource_config) {
    $this->jsonapiResourceConfig = $resource_config;
    if ($name = $resource_config->get('resourceType')) {
      // Set the type name.
      $this->typeName = $name;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function includeCount() {
    return $this->configFactory
      ->get('jsonapi_extras.settings')
      ->get('include_count');
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    $resource_config = $this->getJsonapiResourceConfig();
    if (!$resource_config) {
      return parent::getPath();
    }
    $config_path = $resource_config->get('path');
    if (!$config_path) {
      return parent::getPath();
    }
    return '/' . ltrim($config_path, '/');
  }

  /**
   * Get the resource field configuration.
   *
   * @todo https://www.drupal.org/node/3007820
   *
   * @param string $field_name
   *   The internal field name.
   * @param string $from
   *   The realm of the provided field name.
   *
   * @return array
   *   The resource field definition. NULL if none can be found.
   */
  public function getResourceFieldConfiguration($field_name, $from = 'fieldName') {
    $cid = "$field_name:$from";
    if (isset($this->cache[$cid]) || array_key_exists($cid, $this->cache)) {
      return $this->cache[$cid];
    }

    $resource_fields = $this->getJsonapiResourceConfig()->get('resourceFields');
    // Find the resource field in the config entity for the given field name.
    $found = array_filter($resource_fields, function ($resource_field) use ($field_name, $from) {
      return !empty($resource_field[$from]) &&
        $field_name == $resource_field[$from];
    });
    $result = empty($found) ? NULL : reset($found);
    $this->cache[$cid] = $result;
    return $result;
  }

  /**
   * Injects the config factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The field enhancer manager.
   */
  public function setConfigFactory(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Injects the field enhancer manager.
   *
   * @param \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager $enhancer_manager
   *   The field enhancer manager.
   */
  public function setEnhancerManager(ResourceFieldEnhancerManager $enhancer_manager) {
    $this->enhancerManager = $enhancer_manager;
  }

  /**
   * Get the field enhancer plugin.
   *
   * @param string $field_name
   *   The internal field name.
   * @param string $from
   *   The realm of the provided field name.
   *
   * @return \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerInterface|null
   *   The enhancer plugin. NULL if not found.
   */
  public function getFieldEnhancer($field_name, $from = 'fieldName') {
    if (!$resource_field = $this->getResourceFieldConfiguration($field_name, $from)) {
      return NULL;
    }
    if (empty($resource_field['enhancer']['id'])) {
      return NULL;
    }
    try {
      $enhancer_info = $resource_field['enhancer'];
      // Ensure that the settings are in a suitable format.
      $settings = [];
      if (!empty($enhancer_info['settings']) && is_array($enhancer_info['settings'])) {
        $settings = $enhancer_info['settings'];
      }
      // Get the enhancer instance.
      /** @var \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerInterface $enhancer */
      $enhancer = $this->enhancerManager->createInstance(
        $enhancer_info['id'],
        $settings
      );
      return $enhancer;
    }
    catch (PluginException $exception) {
      return NULL;
    }

  }

}
