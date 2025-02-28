<?php

namespace Drupal\field_validation\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form for FieldValidationRuleSet add and edit forms.
 */
abstract class FieldValidationRuleSetFormBase extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\field_validation\FieldValidationRuleSetInterface
   */
  protected $entity;

  /**
   * The block tabs entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * Constructs a base class for FieldValidationRuleSet add and edit forms.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The block tabs entity storage.
   */
  public function __construct(EntityStorageInterface $entity_storage) {
    $this->entityStorage = $entity_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('field_validation_rule_set')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
  }

}
