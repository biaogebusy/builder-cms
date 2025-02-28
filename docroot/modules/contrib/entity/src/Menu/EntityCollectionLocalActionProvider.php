<?php

namespace Drupal\entity\Menu;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a action link to the add page or add form on the collection.
 */
class EntityCollectionLocalActionProvider implements EntityLocalActionProviderInterface, EntityHandlerInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new EntityCollectionLocalActionProvider object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   */
  public function __construct(EntityTypeInterface $entity_type, TranslationInterface $string_translation) {
    $this->setStringTranslation($string_translation);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static($entity_type, $container->get('string_translation'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildLocalActions(EntityTypeInterface $entity_type) {
    $actions = [];
    if ($entity_type->hasLinkTemplate('collection')) {
      $entity_type_id = $entity_type->id();

      if ($entity_type->hasLinkTemplate('add-page')) {
        $route_name = "entity.$entity_type_id.add_page";
      }
      elseif ($entity_type->hasLinkTemplate('add-form')) {
        $route_name = "entity.$entity_type_id.add_form";
      }

      if (isset($route_name)) {
        $actions[$route_name] = [
          'title' => $this->t('Add @entity', [
            '@entity' => $entity_type->getSingularLabel(),
          ]),
          'route_name' => $route_name,
          'options' => [
            // Redirect back to the collection after form submission.
            'query' => [
              'destination' => $entity_type->getLinkTemplate('collection'),
            ],
          ],
          'appears_on' => ["entity.$entity_type_id.collection"],
        ];
      }
    }
    return $actions;
  }

}
