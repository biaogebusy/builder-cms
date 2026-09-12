<?php

namespace Drupal\commerce_order\Form;

use Drupal\Core\Entity\ContentEntityForm;

/**
 * Provide base form for all order forms.
 */
abstract class OrderFormBase extends ContentEntityForm {

  /**
   * The order entity.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $entity;

}
