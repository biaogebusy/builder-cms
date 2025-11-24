<?php

namespace Drupal\range\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Range views filter.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("range")
 */
class Range extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public $operator = 'within';

  /**
   * {@inheritdoc}
   */
  protected $alwaysMultiple = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['operator'] = ['default' => 'within'];
    $options['value'] = ['default' => ''];
    $options['include_endpoints'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function operatorOptions($which = 'title') {
    $options = [];
    foreach ($this->operators() as $id => $value) {
      $options[$id] = $value[$which];
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'number',
      '#step' => 'any',
      '#title' => $this->t('Value'),
      '#size' => 30,
      '#default_value' => $this->value,
    ];
    if (!$form_state->get('exposed')) {
      $form['include_endpoints'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Include endpoints'),
        '#default_value' => $this->options['include_endpoints'],
        '#description' => $this->t('Whether to include endpoints or not.'),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $field_from = "$this->tableAlias.{$this->definition['additional fields']['from']}";
    $field_to = "$this->tableAlias.{$this->definition['additional fields']['to']}";

    $operators = $this->operators();
    if (!empty($operators[$this->operator]['method'])) {
      $this->{$operators[$this->operator]['method']}($field_from, $field_to);
    }
  }

  /**
   * Operator callback.
   */
  protected function opWithin($field_from, $field_to) {
    $operators = [
      '<', '>',
      '<=', '>=',
    ];

    $include_endpoints = !($this->options['include_endpoints'] xor ($this->operator === 'within'));
    [$op_left, $op_right] = array_slice($operators, $include_endpoints ? 2 : 0, 2);

    if ($this->operator === 'within') {
      $condition = $this->query->getConnection()->condition('AND');
      $this->query->addWhere($this->options['group'], $condition->condition($field_from, $this->value, $op_left)->condition($field_to, $this->value, $op_right));
    }
    else {
      $condition = $this->query->getConnection()->condition('OR');
      $this->query->addWhere($this->options['group'], $condition->condition($field_from, $this->value, $op_right)->condition($field_to, $this->value, $op_left));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if ($this->isAGroup()) {
      return $this->t('grouped');
    }
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }

    $options = $this->operatorOptions('short');
    return $options[$this->operator] . ' ' . $this->value;
  }

  /**
   * Define the operators supported for ranges.
   */
  protected function operators() {
    $operators = [
      'within' => [
        'title' => $this->t('Range contains'),
        'short' => $this->t('contains'),
        'method' => 'opWithin',
        'values' => 1,
      ],
      'not within' => [
        'title' => $this->t('Range does not contain'),
        'short' => $this->t('does not contain'),
        'method' => 'opWithin',
        'values' => 1,
      ],
    ];

    return $operators;
  }

}
