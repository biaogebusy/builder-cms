<?php

namespace Drupal\jsonapi_extras\Plugin\Validation\Constraint;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * The validator.
 */
class DuplicateFieldConstraintValidator extends ConstraintValidator {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * DuplicateFieldConstraintValidator constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager = NULL) {
    $this->entityTypeManager = $entityTypeManager ?: \Drupal::entityTypeManager();
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity_data, Constraint $constraint) {
    $resourceFields = $entity_data['resourceFields'];
    $overrides = [];

    // Get the field values.
    foreach ($resourceFields as $field => $data) {
      // Only get the overridden fields.
      if ($data['fieldName'] != $data['publicName']) {
        // Store the publicName for comparison.
        $overrides[$field] = $data['publicName'];
      }
    }

    // Compare the overrides and find any duplicate values.
    $deduped_overrides = array_unique($overrides);
    $dupes = array_diff_assoc($overrides, $deduped_overrides);
    // Set an error if there are duplicates.
    if ($dupes) {
      foreach ($dupes as $field => $value) {
        $this->context->buildViolation($constraint->message)
          ->atPath("resourceFields.$field.publicName")
          ->addViolation();
      }
    }
    // Now compare the overrides with the default names to validate no dupes
    // exist.
    foreach ($overrides as $field => $override) {
      if (array_key_exists($override, $resourceFields)) {
        $this->context->buildViolation($constraint->message)
          ->atPath("resourceFields.$field.publicName")
          ->addViolation();
      }
    }

    // Validate URL and resource type.
    $resource_types = $this->entityTypeManager
      ->getStorage('jsonapi_resource_config')
      ->loadByProperties(['disabled' => FALSE]);
    foreach ($resource_types as $id => $resource_type) {
      if ($entity_data['id'] == $id) {
        continue;
      }

      if ($resource_type->get('resourceType') == $entity_data['resourceType']) {
        $this->context->buildViolation(
          'There is already resource (@name) with this resource type.',
          ['@name' => $resource_type->id()]
        )
          ->atPath('resourceType')
          ->addViolation();
      }
      if ($resource_type->get('path') == $entity_data['path']) {
        $this->context->buildViolation('There is already resource (@name) with this path.', ['@name' => $resource_type->id()])
          ->atPath('resourceType')
          ->addViolation();
      }
    }
  }

}
