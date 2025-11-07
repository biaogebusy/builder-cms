<?php

declare(strict_types = 1);

namespace Drupal\entity_share\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Alter rich text exposed data to provide import URL for embedded entities.
 *
 * @ResourceFieldEnhancer(
 *   id = "entity_share_embedded_entities",
 *   label = @Translation("Embedded entities (formatted text field only) (Entity Share)"),
 *   description = @Translation("Alter rich text exposed data to provide import URL for embedded entities.")
 * )
 */
class EntityShareEmbeddedEntitiesEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    // Formatted text detection.
    if (!isset($data['format']) || !isset($data['value'])) {
      return $data;
    }

    $data['value'] = preg_replace_callback(
      '#(<.*data-entity-type="(.*)".*data-entity-uuid="(.*)".*)(/?)>#U',
      [self::class, 'addEntityJsonapiUrl'],
      $data['value']
    );

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($value, Context $context) {
    // Formatted text detection.
    if (!isset($value['format']) || !isset($value['value'])) {
      return $value;
    }

    // Remove data-entity-jsonapi-url HTML attribute.
    $value['value'] = preg_replace('# data-entity-jsonapi-url="(.*)"#U', '', $value['value']);

    // For img tag, update the src attribute.
    $value['value'] = preg_replace_callback(
      '#(<img.*data-entity-type="(.*)".*data-entity-uuid="(.*)".*)(/?)>#U',
      [self::class, 'updateImgSrc'],
      $value['value']
    );

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

  /**
   * Helper function to add an entity JSON:API link to HTML attributes.
   *
   * @param array $matches
   *   The link information.
   *
   * @return string
   *   The replacement.
   */
  protected function addEntityJsonapiUrl(array $matches) {
    $entity_type = $matches[2];
    $entity_uuid = $matches[3];

    if (empty($entity_type) || empty($entity_uuid)) {
      return $matches[0];
    }

    $entities = $this->entityTypeManager->getStorage($entity_type)
      ->loadByProperties(['uuid' => $entity_uuid]);
    if (!empty($entities)) {
      $entity = array_shift($entities);
      // Add URL for import.
      $route_name = sprintf('jsonapi.%s--%s.individual', $entity_type, $entity->bundle());
      try {
        $content_url = Url::fromRoute($route_name, [
          'entity' => $entity->uuid(),
        ])
          ->setOption('language', $this->languageManager->getCurrentLanguage())
          ->setOption('absolute', TRUE);

        // For img tag.
        $closing_slash = $matches[4];
        return $matches[1] . ' data-entity-jsonapi-url="' . $content_url->toString() . '"' . $closing_slash . '>';
      }
      catch (\Exception $exception) {
        // Do nothing.
      }
    }
    return $matches[0];
  }

  /**
   * Helper function to update an img tag's src attribute.
   *
   * @param array $matches
   *   The img tag information.
   *
   * @return string
   *   The replacement.
   */
  protected function updateImgSrc(array $matches) {
    $entity_type = $matches[2];
    $entity_uuid = $matches[3];

    if ($entity_type != 'file') {
      return $matches[0];
    }

    $entities = $this->entityTypeManager->getStorage($entity_type)
      ->loadByProperties(['uuid' => $entity_uuid]);
    if (!empty($entities)) {
      /** @var \Drupal\file\FileInterface $entity */
      $entity = array_shift($entities);
      $new_src = $entity->createFileUrl();

      // Insert new src attribute.
      return preg_replace('#src="(.*)"#U', 'src="' . $new_src . '"', $matches[0]);
    }

    return $matches[0];
  }

}
