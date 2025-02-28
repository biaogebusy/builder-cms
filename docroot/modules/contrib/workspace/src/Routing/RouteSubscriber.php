<?php

namespace Drupal\workspace\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\multiversion\MultiversionManagerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for Workspace routes.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * @var \Drupal\multiversion\MultiversionManagerInterface
   */
  protected $multiversionManager;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\multiversion\MultiversionManagerInterface $multiversion_manager
   */
  public function __construct(MultiversionManagerInterface $multiversion_manager) {
    $this->multiversionManager = $multiversion_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->multiversionManager->getEnabledEntityTypes() as $entity_type_id => $entity_type) {
      if ($entity_type->hasLinkTemplate('version-tree')) {
        $options = [
          '_admin_route' => TRUE,
          '_entity_type_id' => $entity_type_id,
          'parameters' => [
            $entity_type_id => [
              'type' => 'entity:' . $entity_type_id,
            ]
          ],
        ];

        if ($link_template = $entity_type->getLinkTemplate('version-tree')) {
          $route = new Route(
            $link_template,
            [
              '_controller' => '\Drupal\workspace\Controller\RevisionsController::revisions',
              '_title' => 'Revisions',
            ],
            // @todo: {@link https://www.drupal.org/node/2596783 Provide more
            // granular permissions.}
            ['_permission' => 'view_revision_trees'],
            $options
          );

          // This will create new routes
          $collection->add("entity.$entity_type_id.version_tree", $route);
        }

        if (($link_template = $entity_type->getLinkTemplate('revision')) && empty($collection->get("entity.$entity_type_id.revision"))) {
          unset($options['_admin_route']);
          $options['parameters']['entity_revision'] = [
            'type' => 'entity_revision:' . $entity_type_id
          ];
          $route = new Route(
            $link_template,
            [
              '_controller' => '\Drupal\workspace\Controller\RevisionController::view',
              '_title_callback' => '\Drupal\workspace\Controller\RevisionController::viewTitle',
            ],
            // @todo: {@link https://www.drupal.org/node/2596783 Provide more
            // granular permissions.}
            ['_permission' => 'view_revision_trees'],
            $options
          );

          // This will create new routes (and override the revision
          // route for entity types that already has one).
          $collection->add("entity.$entity_type_id.revision", $route);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', 100];
    return $events;
  }

}
