<?php

namespace Drupal\jsonapi_extras\EventSubscriber;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvents;
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceTypeRepository;
use Drupal\jsonapi_extras\ResourceType\NullJsonapiResourceConfig;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * JSON API build subscriber that applies all changes from extra's to the API.
 */
class JsonApiBuildSubscriber implements EventSubscriberInterface {

  /**
   * The extra's resource repository.
   *
   * @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceTypeRepository
   */
  private $repository;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * JsonApiBuildSubscriber constructor.
   *
   * @param \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceTypeRepository $repository
   *   Repository from jsonapi_extras is needed to apply configuration.
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $configFactory
   *   Config factory.
   */
  public function __construct(ConfigurableResourceTypeRepository $repository, ConfigFactoryInterface $configFactory = NULL) {
    $this->repository = $repository;
    if ($configFactory === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $configFactory argument is deprecated in jsonapi_extras:8.x-3.20 and will be required in jsonapi_extras:8.x-4.0. See https://www.drupal.org/node/3242191', E_USER_DEPRECATED);
      $configFactory = \Drupal::configFactory();
    }
    $this->configFactory = $configFactory;
  }

  /**
   * What events to subscribe to.
   */
  public static function getSubscribedEvents() {
    $events[ResourceTypeBuildEvents::BUILD][] = ['applyResourceConfig'];
    return $events;
  }

  /**
   * Apply resource config through the event.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent $event
   *   The build event used to change the resources and fields.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function applyResourceConfig(ResourceTypeBuildEvent $event) {
    $resource_config = $this->getResourceConfig($event->getResourceTypeName());
    if ($resource_config instanceof NullJsonapiResourceConfig && $this->configFactory->get('jsonapi_extras.settings')->get('default_disabled')) {
      $event->disableResourceType();
      return;
    }

    if ($resource_config->get('disabled')) {
      $event->disableResourceType();
    }

    $this->overrideFields($resource_config, $event);
  }

  /**
   * Get a single resource configuration entity by its ID.
   *
   * @param string $resource_config_id
   *   The configuration entity ID.
   *
   * @return \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig
   *   The configuration entity for the resource type.
   */
  protected function getResourceConfig($resource_config_id) {
    $null_resource = new NullJsonapiResourceConfig(
          ['id' => $resource_config_id],
          'jsonapi_resource_config'
      );
    try {
      $resource_configs = $this->repository->getResourceConfigs();
      return $resource_configs[$resource_config_id] ?? $null_resource;
    }
    catch (PluginException $e) {
      return $null_resource;
    }
  }

  /**
   * Gets the fields for the given field names and entity type + bundle.
   *
   * @param \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig $resource_config
   *   The associated resource config.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent $event
   *   The associated resource config.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function overrideFields(JsonapiResourceConfig $resource_config, ResourceTypeBuildEvent $event) {
    // Use the base class to fetch the non-configurable field mappings.
    $mappings = $resource_config->getFieldMapping();
    // Ignore all the fields that don't have aliases.
    $mappings = array_filter($mappings, function ($field_info) {
        return $field_info !== TRUE;
    });

    $fields = $event->getFields();

    foreach ($mappings as $internal_name => $mapping) {
      if (!isset($fields[$internal_name])) {
        continue;
      }
      if (is_string($mapping)) {
        $event->setPublicFieldName($fields[$internal_name], $mapping);
      }
      if ($mapping === FALSE) {
        $event->disableField($fields[$internal_name]);
      }
    }
  }

}
