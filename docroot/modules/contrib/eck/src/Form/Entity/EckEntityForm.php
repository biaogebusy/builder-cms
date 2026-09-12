<?php

namespace Drupal\eck\Form\Entity;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the ECK entity forms.
 *
 * @ingroup eck
 */
class EckEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\eck\Entity\EckEntity $entity */
    $entity = $this->entity;

    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('<em>Edit @type</em> @title', [
        '@type' => $entity->type->entity->label(),
        '@title' => $entity->label(),
      ]);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $saved = parent::save($form, $form_state);
    $logger = $this->logger('eck');
    $entity_link = $this->entity->toLink($this->t('View'))->toString();
    $context = [
      '@type' => $this->entity->bundle(),
      'link' => $entity_link,
    ];

    if ($label = $this->entity->label()) {
      $context['%label'] = $label;
      $t_args = [
        '@type' => $this->entity->type->entity->label(),
        '%label' => $this->entity->toLink($label)->toString(),
      ];

      if ($saved === SAVED_NEW) {
        $logger->notice('@type: added %label.', $context);
        $this->messenger()->addStatus($this->t('@type %label has been created.', $t_args));
      }
      else {
        $logger->notice('@type: updated %label', $context);
        $this->messenger()->addStatus($this->t('@type %label has been updated.', $t_args));
      }
    }
    else {
      $t_args = [
        '%type' => $this->entity->toLink($this->entity->type->entity->label())->toString(),
      ];

      if ($saved === SAVED_NEW) {
        $logger->notice('@type: added entity.', $context);
        $this->messenger()->addStatus($this->t('%type has been created.', $t_args));
      }
      else {
        $logger->notice('@type: updated entity.', $context);
        $this->messenger()->addStatus($this->t('%type has been updated.', $t_args));
      }
    }

    $form_state->setRedirect('entity.' . $this->entity->getEntityTypeId() . '.canonical', [$this->entity->getEntityTypeId() => $this->entity->id()]);
    return $saved;
  }

}
