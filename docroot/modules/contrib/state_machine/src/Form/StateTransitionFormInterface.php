<?php

namespace Drupal\state_machine\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\BaseFormIdInterface;

/**
 * Defines the interface for state transition forms.
 *
 * Used for applying a transition to the form entity's state field.
 */
interface StateTransitionFormInterface extends BaseFormIdInterface {

  /**
   * Gets the form entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The form entity.
   */
  public function getEntity();

  /**
   * Sets the form entity.
   *
   * When the form is submitted, a transition will be applied to the entity,
   * and the entity will be saved.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The form entity.
   *
   * @return $this
   */
  public function setEntity(ContentEntityInterface $entity);

  /**
   * Gets the state field name.
   *
   * @return string
   *   The state field name.
   */
  public function getFieldName();

  /**
   * Sets the state field name.
   *
   * @param string $field_name
   *   The state field name.
   *
   * @return $this
   */
  public function setFieldName($field_name);

}
