<?php

namespace Drupal\state_machine\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for state machine transition routes on entities.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityTypeManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->hasLinkTemplate('state-transition-form')) {
        continue;
      }
      $route = new Route($entity_type->getLinkTemplate('state-transition-form'));
      $route
        ->setDefaults([
          '_entity_form' => "$entity_type_id.state-transition-confirm",
        ])
        ->setRequirement('_state_transition_access', "TRUE")
        ->setRequirement($entity_type_id, '\d+')
        ->setRequirement('transition_id', '[a-z0-9_]+')
        ->setRequirement('field_name', '[a-z0-9_]+')
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      $collection->add("entity.$entity_type_id.state_transition_form", $route);
    }
  }

}
