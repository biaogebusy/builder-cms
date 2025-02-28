<?php

namespace Drupal\inline_entity_form_test;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Tests Inline entity form element.
 */
class IefTest extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ief_test';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $form_mode = 'default', Node $node = NULL) {
    $form['inline_entity_form'] = [
      '#type' => 'inline_entity_form',
      '#entity_type' => 'node',
      '#bundle' => 'ief_test_custom',
      '#form_mode' => $form_mode,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    if (!empty($node)) {
      $form['inline_entity_form']['#default_value'] = $node;
      $form['submit']['#value'] = $this->t('Update');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $form['inline_entity_form']['#entity'];
    $message = $this->t(
      'Created @entity_type @label.',
      [
        '@entity_type' => $entity->getEntityType()->getLabel(),
        '@label' => $entity->label(),
      ]
    );
    $this->messenger()->addMessage($message);
  }

}
