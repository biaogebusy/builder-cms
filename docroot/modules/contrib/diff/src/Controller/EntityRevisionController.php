<?php

declare(strict_types=1);

namespace Drupal\diff\Controller;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\diff\Form\EntityRevisionOverviewForm;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for entity revision overview.
 */
class EntityRevisionController extends PluginRevisionController {

  /**
   * Returns a form for revision overview page.
   */
  public function revisionOverview(RouteMatchInterface $route_match): array {
    $entity_type_id = $route_match->getRouteObject()->getDefault('_entity_revisions_overview');
    $entity = $this->entityTypeManager()->getStorage($entity_type_id)->load($route_match->getRawParameter($entity_type_id));
    if ($entity === NULL) {
      throw new NotFoundHttpException();
    }

    return $this->formBuilder()->getForm(EntityRevisionOverviewForm::class, $entity);
  }

}
