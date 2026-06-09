<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides HTML routes for entities with administrative pages.
 */
final class AutomatorChainTypeHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type): array|RouteCollection {
    $collection = parent::getRoutes($entity_type);

    $collection->addRequirements(['_automator_advanced' => TRUE]);

    /** @var \Symfony\Component\Routing\Route $route */
    $route = $collection->get('entity.automator_chain_type.collection');
    $route->setDefault('_title', 'Automator Chain settings');
    $collection->add('entity.automator_chain_type.collection', $route);

    return $collection;
  }

}
