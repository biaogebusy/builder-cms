<?php

namespace Drupal\eck;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eck\Entity\EckEntityType;

/**
 * Defines dynamic permissions.
 *
 * @ingroup eck
 */
class PermissionsGenerator {

  use StringTranslationTrait;

  /**
   * Returns an array of entity type permissions.
   *
   * @return array
   *   The permissions.
   */
  public function entityPermissions() {
    $perms = [];
    // Generate entity permissions for all entity types.
    foreach (EckEntityType::loadMultiple() as $eck_type) {
      $perms = array_merge($perms, $this->buildPermissions($eck_type));
    }

    return $perms;
  }

  /**
   * Builds a standard list of entity permissions for a given type.
   *
   * @param \Drupal\eck\Entity\EckEntityType $eck_type
   *   The entity type.
   *
   * @return array
   *   An array of permissions.
   */
  private function buildPermissions(EckEntityType $eck_type) {
    return array_merge(
      $this->getCreatePermission($eck_type),
      $this->getEditPermissions($eck_type),
      $this->getListingPermission($eck_type)
    );
  }

  /**
   * Retrieves the listing permission for a given entity type.
   *
   * @param \Drupal\eck\Entity\EckEntityType $entity_type
   *   The entity type.
   *
   * @return array
   *   The listing permission.
   */
  private function getListingPermission(EckEntityType $entity_type) {
    return [
      "access {$entity_type->id()} entity listing" => [
        'title' => $this->t('Access %type_name listing page', ['%type_name' => $entity_type->label()]),
      ],
    ];
  }

  /**
   * Retrieves the create permission for a given entity type.
   *
   * @param \Drupal\eck\Entity\EckEntityType $entity_type
   *   The entity type.
   *
   * @return array
   *   The create permission.
   */
  private function getCreatePermission(EckEntityType $entity_type) {
    return [
      "create {$entity_type->id()} entities" => [
        'title' => $this->t('Create new %type_name entities', ['%type_name' => $entity_type->label()]),
      ],
    ];
  }

  /**
   * Retrieves the edit permissions for a given entity type.
   *
   * @param \Drupal\eck\Entity\EckEntityType $entity_type
   *   The entity type.
   *
   * @return array
   *   The edit permission.
   */
  private function getEditPermissions(EckEntityType $entity_type) {
    $permissions = [];
    foreach (['edit', 'delete', 'view'] as $op) {
      $permissions = array_merge($permissions, $this->getEditPermission($entity_type, $op, 'any'));
      if ($entity_type->hasAuthorField()) {
        $permissions = array_merge($permissions, $this->getEditPermission($entity_type, $op, 'own'));
      }
    }
    return $permissions;
  }

  /**
   * Retrieves the edit permission for a given entity type.
   *
   * @param \Drupal\eck\Entity\EckEntityType $entity_type
   *   The entity type.
   * @param string $op
   *   The operation.
   * @param string $ownership
   *   The ownership.
   *
   * @return array
   *   The edit permission.
   */
  private function getEditPermission(EckEntityType $entity_type, $op, $ownership) {
    $ucfirst_op = ucfirst($op);
    $typeLabel = $entity_type->label();

    $title = $this->t('@operation @ownership %type_name entities', [
      '@operation' => $ucfirst_op,
      '@ownership' => $ownership,
      '%type_name' => $typeLabel,
    ]);

    return [
      "{$op} {$ownership} {$entity_type->id()} entities" => [
        'title' => $title,
      ],
    ];
  }

}
