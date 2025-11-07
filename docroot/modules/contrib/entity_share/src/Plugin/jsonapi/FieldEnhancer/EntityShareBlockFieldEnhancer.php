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
 * Prepare block field value to be able to handle block content entities.
 *
 * @ResourceFieldEnhancer(
 *   id = "entity_share_block_field",
 *   label = @Translation("Block field (Block field only) (Entity Share)"),
 *   description = @Translation("Prepare block field value to be able to handle block content entities."),
 *   dependencies = {"block_content", "block_field"}
 * )
 */
class EntityShareBlockFieldEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

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

    if (isset($data['settings']['id'])) {
      $parsed_id = [];
      // Check if it is a link to an entity.
      preg_match("/block_content:(.*)/", $data['settings']['id'], $parsed_id);
      if (!empty($parsed_id)) {
        $block_content_uuid = $parsed_id[1];
        /** @var \Drupal\block_content\BlockContentInterface[] $block_content */
        $block_contents = $this->entityTypeManager->getStorage('block_content')
          ->loadByProperties([
            'uuid' => $block_content_uuid,
          ]);
        if (!empty($block_contents)) {
          $block_content = array_shift($block_contents);
          $route_name = sprintf('jsonapi.%s--%s.individual', 'block_content', $block_content->bundle());
          $url = Url::fromRoute($route_name, [
            'entity' => $block_content_uuid,
          ])
            ->setOption('language', $this->languageManager->getCurrentLanguage())
            ->setOption('absolute', TRUE);

          $data['block_content_href'] = $url->toString();
        }
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($value, Context $context) {
    if (isset($value['block_content_href'])) {
      unset($value['block_content_href']);
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
