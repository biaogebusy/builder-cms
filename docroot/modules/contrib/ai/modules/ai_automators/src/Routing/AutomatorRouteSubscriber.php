<?php

namespace Drupal\ai_automators\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes.
 */
class AutomatorRouteSubscriber implements ContainerInjectionInterface {

  /**
   * Constructs a new AutomatorRouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Provides dynamic routes.
   */
  public function routes(): array {
    $routes = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements('Drupal\Core\Config\Entity\ConfigEntityInterface')) {
        continue;
      }
      if (!$entity_type->getBundleOf()) {
        continue;
      }

      // Field UI base routes on these entities tend to be based around the
      // canonical path, so we will use this as our base for this route. If this
      // isn't implemented, the edit form link is a suitable proxy for it.
      if (!$entity_type->hasLinkTemplate('edit-form') && !$entity_type->hasLinkTemplate('canonical')) {
        continue;
      }

      $path = $entity_type->hasLinkTemplate('canonical') ? $entity_type->getLinkTemplate('canonical') : $entity_type->getLinkTemplate('edit-form');

      // But sometimes the edit-form link is itself a sub-path, usually ending
      // with "/edit" so we will accommodate for that.
      if (str_ends_with($path, '/edit')) {
        $path = substr($path, -5);
      }

      // If the path doesn't have the entity_type parameter, we'll need to add
      // it.
      if (!str_contains($path, '{' . $entity_type_id . '}')) {
        $path = $path . '/{' . $entity_type_id . '}';
      }

      // Now we can just add our identifier to the end of the path.
      $path .= '/automator_chain';

      // $path = $entity_route->getPath();
      $route = new Route(
        $path,
        [
          '_form' => '\Drupal\ai_automators\Form\AiChainForm',
          '_title' => 'AI Automator Run Order',
        ],
        [
          '_permission'  => 'administer ai_automator',
        ]
      );
      $route->addOptions([
        'parameters' => [
          $entity_type_id => [
            'type' => 'entity:' . $entity_type_id,
          ],
        ],
      ]);
      $routes['ai_automator.config_chain.' . $entity_type->getBundleOf()] = $route;
    }
    return $routes;
  }

}
