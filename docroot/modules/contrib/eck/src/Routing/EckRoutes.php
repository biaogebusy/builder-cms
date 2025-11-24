<?php

namespace Drupal\eck\Routing;

use Drupal\eck\Entity\EckEntityType;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes.
 *
 * @ingroup eck
 */
class EckRoutes {

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routeCollection = new RouteCollection();

    /** @var \Drupal\eck\Entity\EckEntityType $entityType */
    foreach (EckEntityType::loadMultiple() as $entityType) {
      $entityTypeId = $entityType->id();
      $entityTypeLabel = $entityType->label();

      $routeCollection->add("eck.entity.{$entityTypeId}.list", $this->createListRoute($entityTypeId, $entityTypeLabel));
      $routeCollection->add("eck.entity.{$entityTypeId}_type.list", $this->createBundleListRoute($entityTypeId, $entityTypeLabel));
      $routeCollection->add("eck.entity.{$entityTypeId}_type.add", $this->createAddBundleRoute($entityTypeId, $entityTypeLabel));
      $routeCollection->add("entity.{$entityTypeId}_type.edit_form", $this->createEditBundleRoute($entityTypeId, $entityTypeLabel));
      $routeCollection->add("entity.{$entityTypeId}_type.delete_form", $this->createDeleteBundleRoute($entityTypeId, $entityTypeLabel));
    }
    return $routeCollection;
  }

  /**
   * Creates the listing route.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string $entityTypeLabel
   *   The entity type label.
   *
   * @return \Symfony\Component\Routing\Route
   *   The created listing route.
   */
  private function createListRoute($entityTypeId, $entityTypeLabel) {
    $path = "admin/content/{$entityTypeId}";
    $defaults = [
      '_entity_list' => $entityTypeId,
      '_title' => '%type content',
      '_title_arguments' => ['%type' => ucfirst($entityTypeLabel)],
    ];
    $permissions = [
      "access {$entityTypeId} entity listing",
      "bypass eck entity access",
    ];
    $requirements = ['_permission' => implode('+', $permissions)];
    return new Route($path, $defaults, $requirements);
  }

  /**
   * Creates the bundle listing route.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string $entityTypeLabel
   *   The entity type label.
   *
   * @return \Symfony\Component\Routing\Route
   *   The created bundle listing route.
   */
  private function createBundleListRoute($entityTypeId, $entityTypeLabel) {
    $path = "admin/structure/eck/{$entityTypeId}/bundles";
    $defaults = [
      '_controller' => '\Drupal\Core\Entity\Controller\EntityListController::listing',
      'entity_type' => "{$entityTypeId}_type",
      '_title' => '%type bundles',
      '_title_arguments' => ['%type' => ucfirst($entityTypeLabel)],
    ];
    return new Route($path, $defaults, $this->getBundleRouteRequirements());
  }

  /**
   * Retrieves the bundle route requirements.
   *
   * @return array
   *   The bundle route requirements.
   */
  private function getBundleRouteRequirements() {
    return ['_permission' => 'administer eck entity bundles'];
  }

  /**
   * Creates the add bundle route.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string $entityTypeLabel
   *   The entity type label.
   *
   * @return \Symfony\Component\Routing\Route
   *   The add bundle route.
   */
  private function createAddBundleRoute($entityTypeId, $entityTypeLabel) {
    $path = "admin/structure/eck/{$entityTypeId}/bundles/add";
    return $this->createBundleCrudRoute($entityTypeId, $entityTypeLabel, $path, "add");
  }

  /**
   * Creates a bundle crud route.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string $entityTypeLabel
   *   The entity type label.
   * @param string $path
   *   The path.
   * @param string $op
   *   The operation.
   *
   * @return \Symfony\Component\Routing\Route
   *   The bundle crud route.
   */
  private function createBundleCrudRoute($entityTypeId, $entityTypeLabel, $path, $op) {
    $defaults = [
      '_entity_form' => "{$entityTypeId}_type.{$op}",
      '_title' => ucfirst("{$op} %type bundle"),
      '_title_arguments' => ['%type' => $entityTypeLabel],
    ];
    return new Route($path, $defaults, $this->getBundleRouteRequirements());
  }

  /**
   * Creates the edit bundle route.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string $entityTypeLabel
   *   The entity type label.
   *
   * @return \Symfony\Component\Routing\Route
   *   The edit bundle route.
   */
  private function createEditBundleRoute($entityTypeId, $entityTypeLabel) {
    $path = "admin/structure/eck/{$entityTypeId}/bundles/{{$entityTypeId}_type}";
    return $this->createBundleCrudRoute($entityTypeId, $entityTypeLabel, $path, "edit");
  }

  /**
   * Creates the delete bundle route.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string $entityTypeLabel
   *   The entity type label.
   *
   * @return \Symfony\Component\Routing\Route
   *   The delete bundle route.
   */
  private function createDeleteBundleRoute($entityTypeId, $entityTypeLabel) {
    $path = "admin/structure/eck/{$entityTypeId}/bundles/{{$entityTypeId}_type}/delete";
    return $this->createBundleCrudRoute($entityTypeId, $entityTypeLabel, $path, "delete");
  }

}
