<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides HTML routes for entities with administrative pages.
 */
final class AutomatorChainHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type): RouteCollection|array {
    $collection = parent::getRoutes($entity_type);

    $collection->addRequirements(['_automator_advanced' => TRUE]);

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type): ?Route {
    return $this->getEditFormRoute($entity_type);
  }

}
