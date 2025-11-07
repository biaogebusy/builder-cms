<?php

declare(strict_types = 1);

namespace Drupal\entity_share\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\Url;
use Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer\UuidLinkEnhancer;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Use UUID for internal link field value and add URL for import.
 *
 * @ResourceFieldEnhancer(
 *   id = "entity_share_uuid_link",
 *   label = @Translation("UUID for link (link field only) (Entity Share)"),
 *   description = @Translation("Use UUID for internal link field and provide import URL.")
 * )
 */
class EntityShareUuidLinkEnhancer extends UuidLinkEnhancer {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->languageManager = $container->get('language_manager');
    return $instance;
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

          // Add URL for import.
          $route_name = sprintf('jsonapi.%s--%s.individual', $entity_type, $entity->bundle());
          try {
            $content_url = Url::fromRoute($route_name, [
              'entity' => $entity->uuid(),
            ])
              ->setOption('language', $this->languageManager->getCurrentLanguage())
              ->setOption('absolute', TRUE);

            $data['content_entity_href'] = $content_url->toString();
          }
          catch (\Exception $exception) {
            // Do nothing.
          }
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
    if (isset($value['content_entity_href'])) {
      unset($value['content_entity_href']);
    }
    return parent::doTransform($value, $context);
  }

}
