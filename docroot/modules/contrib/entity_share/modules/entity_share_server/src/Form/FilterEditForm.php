<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_share_server\OperatorsHelper;

/**
 * Form to edit a filter on a channel.
 *
 * @package Drupal\entity_share_server\Form
 */
class FilterEditForm extends FilterBaseForm {

  /**
   * The filter id.
   *
   * @var string
   */
  protected $filterId;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Check if the filter exists.
    if (!$this->filterIdExists()) {
      $this->messenger()->addError($this->t('There is no filter with the ID @id in this channel', [
        '@id' => $this->getFilterId(),
      ]));

      return [];
    }
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_filters = $channel->get('channel_filters');
    $filter_id = $this->getFilterId();

    $form['#tree'] = TRUE;
    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#description' => $this->t('Enter the machine name of the field / property you want to filter on. You can reference field / property of a referenced entity. Example: uid.name for the name of the author.'),
      '#required' => TRUE,
      '#default_value' => $channel_filters[$filter_id]['path'],
    ];

    $form['filter_id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#default_value' => $filter_id,
      '#machine_name' => [
        'source' => ['path'],
        'exists' => [$this, 'filterExists'],
      ],
      '#disabled' => TRUE,
    ];

    $form['operator'] = [
      '#type' => 'select',
      '#title' => $this->t('Operator'),
      '#options' => OperatorsHelper::getOperatorOptions(),
      '#empty_option' => $this->t('Select an operator'),
      '#default_value' => $channel_filters[$filter_id]['operator'],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxValueElement'],
        'effect' => 'fade',
        'method' => 'replace',
        'wrapper' => 'value-wrapper',
      ],
    ];
    // Container for the AJAX.
    $form['value_wrapper'] = [
      '#type' => 'container',
      // Force an id because otherwise default id is changed when using AJAX.
      '#attributes' => [
        'id' => 'value-wrapper',
      ],
    ];

    $this->buildValueElement($form, $form_state);

    $form['memberof'] = [
      '#type' => 'select',
      '#title' => $this->t('Parent group'),
      '#options' => $this->getGroupOptions(),
      '#empty_option' => $this->t('Select a group'),
      '#default_value' => isset($channel_filters[$filter_id]['memberof']) ? $channel_filters[$filter_id]['memberof'] : '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_filters = $channel->get('channel_filters');

    // Add the filter.
    $edited_filter = [
      'path' => $form_state->getValue('path'),
      'operator' => $form_state->getValue('operator'),
    ];

    $value = $form_state->getValue(['value_wrapper', 'value']);
    if (!is_null($value)) {
      $edited_filter['value'] = array_filter($value);
    }

    $memberof = $form_state->getValue('memberof');
    if (!empty($memberof)) {
      $edited_filter['memberof'] = $memberof;
    }

    $channel_filters[$form_state->getValue('filter_id')] = $edited_filter;
    $channel->set('channel_filters', $channel_filters);
    $channel->save();

    $form_state->setRedirectUrl($channel->toUrl('edit-form'));
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    if (!$this->filterIdExists()) {
      return [];
    }

    return parent::actions($form, $form_state);
  }

  /**
   * Helper function to generate filter form elements.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function buildValueElement(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_filters = $channel->get('channel_filters');
    $filter_id = $this->getFilterId();
    $filter_operator = $channel_filters[$filter_id]['operator'];
    $selected_operator = $form_state->getValue('operator');

    // No operator selected and the filter does not have any (which should not
    // happen).
    if (empty($selected_operator) && $filter_operator == '') {
      return;
    }

    if (!empty($selected_operator)) {
      $operator = $selected_operator;
    }
    else {
      $operator = $filter_operator;
    }

    // Operators which do not require value.
    if (in_array($operator, OperatorsHelper::getStandAloneOperators())) {
      return;
    }

    // Check the number of values.
    $number_of_values = $form_state->get('number_of_values');
    // We have to ensure that there is at least one value field.
    if ($number_of_values === NULL) {
      if (isset($channel_filters[$filter_id]['value'])) {
        $number_of_values = count($channel_filters[$filter_id]['value']);
      }
      else {
        $number_of_values = 1;
      }

      $form_state->set('number_of_values', $number_of_values);
    }

    for ($i = 0; $i < $number_of_values; $i++) {
      $form['value_wrapper']['value'][$i] = [
        '#type' => 'textfield',
        '#title' => $this->t('Value'),
        '#default_value' => isset($channel_filters[$filter_id]['value'][$i]) ? $channel_filters[$filter_id]['value'][$i] : '',
      ];
    }

    $form['value_wrapper']['actions'] = [
      '#type' => 'actions',
    ];
    if (in_array($operator, OperatorsHelper::getMultipleValuesOperators())) {
      $form['value_wrapper']['actions']['add_one_value'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add a value'),
        '#submit' => ['::addOneValue'],
        '#ajax' => [
          'callback' => '::addRemoveCallback',
          'wrapper' => 'value-wrapper',
        ],
      ];
    }
    // If there is more than one name, add the remove button.
    if ($number_of_values > 1) {
      $form['value_wrapper']['actions']['remove_one_value'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove a value'),
        '#submit' => ['::removeOneValue'],
        '#ajax' => [
          'callback' => '::addRemoveCallback',
          'wrapper' => 'value-wrapper',
        ],
      ];
    }
  }

}
