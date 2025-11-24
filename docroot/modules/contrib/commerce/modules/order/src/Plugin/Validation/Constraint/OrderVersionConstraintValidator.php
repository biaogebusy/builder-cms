<?php

namespace Drupal\commerce_order\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the OrderVersion constraint.
 */
class OrderVersionConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if (isset($entity) && !$entity->isNew()) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $saved_entity */
      $saved_entity = $this->entityTypeManager
        ->getStorage($entity->getEntityTypeId())
        ->loadUnchanged($entity->id());
      // A change to the order version must add a violation.
      if ($saved_entity && $saved_entity->getVersion() > $entity->getVersion()) {
        $this->context->addViolation($constraint->message);
      }
    }
  }

}
