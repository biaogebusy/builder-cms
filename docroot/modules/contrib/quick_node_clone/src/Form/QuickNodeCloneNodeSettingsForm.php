<?php

namespace Drupal\quick_node_clone\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Module settings form.
 */
class QuickNodeCloneNodeSettingsForm extends QuickNodeCloneEntitySettingsForm {

  /**
   * The machine name of the entity type.
   *
   * @var string
   *   The entity type id i.e. node
   */
  protected $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'quick_node_clone_node_setting_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['text_to_prepend_to_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text to prepend to title'),
      '#default_value' => $this->getSettings('text_to_prepend_to_title'),
      '#description' => $this->t('Enter text to add to the title of a cloned node to help content editors. A space will be added between this text and the title. Example: "Clone of"'),
    ];
    $form['clone_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clone publication status of original?'),
      '#default_value' => $this->getSettings('clone_status'),
      '#description' => $this->t('If unchecked, the publication status of the clone will be equal to the default of the content type.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $form_values = $form_state->getValues();
    $this->config('quick_node_clone.settings')->set('text_to_prepend_to_title', $form_values['text_to_prepend_to_title'])->save();
    $this->config('quick_node_clone.settings')->set('clone_status', $form_values['clone_status'])->save();

    parent::submitForm($form, $form_state);
  }

}
