<?php

namespace Drupal\bootstrap_layout_builder\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;

/**
 * Builds the layout options form.
 *
 * @internal
 */
class LayoutOptionsForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\bootstrap_layout_builder\Layout
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['layout_option'] = [
      '#tree' => TRUE,
      '#weight' => -20,
    ];

    $form['layout_option']['links'] = [
      '#type' => 'table',
      '#header' => [$this->t('Label'), $this->t('Structure'), $this->t('Breakpoints'), $this->t('Weight'), $this->t('Default Option For'), $this->t('Operations')],
      '#empty' => $this->t('No layout options available. <a href=":link">Add a layout option</a>', [':link' => Url::fromRoute('entity.blb_layout_option.add_form', ['blb_layout' => $this->entity->id()])->toString()]),
      '#attributes' => ['id' => 'layout_option'],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'layout-option-weight',
        ],
      ],
    ];

    foreach ($this->entity->getLayoutOptions() as $option) {
      $id = $option->id();
      $form['layout_option']['links'][$id]['#attributes']['class'][] = 'draggable';
      $form['layout_option']['links'][$id]['label'] = [
        '#type' => 'label',
        '#title' => $option->label(),
      ];
      $form['layout_option']['links'][$id]['structure'] = [
        '#type' => 'label',
        '#title' => $option->getStructure(),
      ];
      $form['layout_option']['links'][$id]['breakpoins'] = [
        '#type' => 'label',
        '#title' => implode(', ', $option->getBreakpointsLabels()),
      ];
      $form['layout_option']['links'][$id]['#weight'] = $option->getWeight();
      $form['layout_option']['links'][$id]['weight'] = [
        '#type' => 'weight',
        '#title' => t('Weight for @title', ['@title' => $option->label()]),
        '#title_display' => 'invisible',
        '#default_value' => $option->getWeight(),
        '#attributes' => ['class' => ['layout-option-weight']],
      ];

      $form['layout_option']['links'][$id]['default_breakpoints'] = [
        '#type' => 'label',
        '#title' => $option->getDefaultBreakpointsLabels() ? implode(', ', $option->getDefaultBreakpointsLabels()) : '',
      ];

      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $option->toUrl('edit-form'),
      ];
      $links['delete'] = [
        'title' => t('Delete'),
        'url' => $option->toUrl('delete-form'),
      ];
      $form['layout_option']['links'][$id]['operations'] = [
        '#type' => 'operations',
        '#links' => $links,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // Only includes a Save action for the entity, no direct Delete button.
    return [
      'submit' => [
        '#type' => 'submit',
        '#value' => t('Save'),
        '#access' => (bool) Element::getVisibleChildren($form['layout_option']['links']),
        '#submit' => ['::submitForm', '::save'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    foreach ($this->entity->getLayoutOptions() as $option) {
      $weight = $form_state->getValue(['layout_option', 'links', $option->id(), 'weight']);
      $option->setWeight($weight);
      $option->save();
    }
    $this->messenger()->addStatus($this->t('The layout options has been updated.'));
  }

}
