<?php

namespace Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Use UUID for internal link field value.
 *
 * @ResourceFieldEnhancer(
 *   id = "uuid_link",
 *   label = @Translation("UUID for link (link field only)"),
 *   description = @Translation("Use UUID for internal link field.")
 * )
 */
class UuidLinkEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    if (isset($data['uri'])) {
      // Check if it is a link to an entity.
      preg_match("/entity:(.*)\/(.*)/", $data['uri'], $parsed_uri);
      if (!empty($parsed_uri)) {
        $entity_type = $parsed_uri[1];
        $entity_id = $parsed_uri[2];
        $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
        if (!is_null($entity)) {
          $data['uri'] = 'entity:' . $entity_type . '/' . $entity->bundle() . '/' . $entity->uuid();
        }
        // Remove the value.
        else {
          $data = [
            'uri' => '',
            'title' => '',
            'options' => [],
          ];
        }
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($value, Context $context) {
    if (isset($value['uri'])) {
      // Check if it is a link to an entity.
      preg_match("/entity:(.*)\/(.*)\/(.*)/", $value['uri'], $parsed_uri);
      if (!empty($parsed_uri)) {
        $entity_type = $parsed_uri[1];
        $entity_uuid = $parsed_uri[3];
        $entities = $this->entityTypeManager->getStorage($entity_type)->loadByProperties(['uuid' => $entity_uuid]);
        if (!empty($entities)) {
          $entity = array_shift($entities);
          $value['uri'] = 'entity:' . $entity_type . '/' . $entity->id();
        }
        else {
          // If the entity has not been imported yet we unset the field value.
          $value = [];
        }
      }
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'type' => 'object',
    ];
  }

}
