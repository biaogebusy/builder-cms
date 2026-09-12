<?php

namespace Drupal\content_lock\Routing;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Builds up the content lock routes on all content entities.
 *
 * @package Drupal\content_lock\Routing
 */
class ContentLockRoutes implements ContainerInjectionInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ContentLockInterface $contentLock,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('content_lock'),
    );
  }

  /**
   * Creates routes for each entity type that uses content locking.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   The array of routes.
   */
  public function routes(): array {
    $routes = [];

    $definitions = $this->entityTypeManager->getDefinitions();
    foreach ($definitions as $definition) {
      if ($definition instanceof ContentEntityTypeInterface && $this->contentLock->hasLockEnabled($definition->id())) {
        $routes['content_lock.break_lock.' . $definition->id()] = new Route(
          '/admin/lock/break/' . $definition->id() . '/{entity}/{langcode}/{form_op}',
          [
            '_form' => $definition->getHandlerClass('break_lock_form'),
            '_title' => 'Break lock',
          ],
          [
            '_custom_access' => $definition->getHandlerClass('break_lock_form') . '::access',
          ],
          [
            '_admin_route' => TRUE,
            'parameters' => [
              'entity' => [
                'type' => 'entity:' . $definition->id(),
              ],
            ],
          ]
        );
      }
    }
    return $routes;
  }

}
