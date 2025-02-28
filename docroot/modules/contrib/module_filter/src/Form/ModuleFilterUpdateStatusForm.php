<?php

namespace Drupal\module_filter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A form for filtering the update status report page.
 */
class ModuleFilterUpdateStatusForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'module_filter_update_status_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['table-filter', 'js-show'],
      ],
    ];

    $form['filters']['text'] = [
      '#type' => 'search',
      '#title' => $this->t('Filter projects'),
      '#title_display' => 'invisible',
      '#size' => 30,
      '#placeholder' => $this->t('Filter by project'),
      '#attributes' => [
        'class' => ['table-filter-text'],
        'data-table' => '#update-status',
        'autocomplete' => 'off',
      ],
      '#attached' => [
        'library' => [
          'module_filter/update.status',
        ],
      ],
    ];
    if (!empty($this->getRequest()->query->get('filter'))) {
      $form['filters']['text']['#default_value'] = $this->getRequest()->query->get('filter');
    }

    $form['filters']['radios'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'module-filter-status',
        ],
      ],
      'show' => [
        '#type' => 'radios',
        '#default_value' => 'all',
        '#options' => [
          'all' => $this->t('All'),
          'updates' => $this->t('Update available'),
          'security' => $this->t('Security update'),
          'unsupported' => $this->t('Unsupported'),
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
