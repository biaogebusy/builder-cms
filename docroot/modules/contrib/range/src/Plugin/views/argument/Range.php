<?php

namespace Drupal\range\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;

/**
 * Range views argument.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("range")
 */
class Range extends ArgumentPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['operator'] = ['default' => 'within', 'bool' => FALSE];
    $options['include_endpoints'] = ['default' => FALSE, 'bool' => TRUE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Operator'),
      '#options' => [
        'within' => $this->t('Range contains'),
        'not within' => $this->t('Range does not contain'),
      ],
      '#default_value' => $this->options['operator'],
      '#group' => 'options][more',
    ];
    $form['include_endpoints'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include endpoints'),
      '#default_value' => !empty($this->options['include_endpoints']),
      '#description' => $this->t('Whether to include endpoints or not.'),
      '#group' => 'options][more',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    $field_from = "$this->tableAlias.{$this->definition['additional fields']['from']}";
    $field_to = "$this->tableAlias.{$this->definition['additional fields']['to']}";

    $operator = $this->options['operator'];
    $operators = $this->operators();
    if (!empty($operators[$operator]['method'])) {
      $this->{$operators[$operator]['method']}($field_from, $field_to);
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

    $include_endpoints = !($this->options['include_endpoints'] xor ($this->options['operator'] === 'within'));
    [$op_left, $op_right] = array_slice($operators, $include_endpoints ? 2 : 0, 2);

    if ($this->options['operator'] === 'within') {
      $condition = $this->query->getConnection()->condition('AND');
      $this->query->addWhere(0, $condition->condition($field_from, $this->argument, $op_left)->condition($field_to, $this->argument, $op_right));
    }
    else {
      $condition = $this->query->getConnection()->condition('OR');
      $this->query->addWhere(0, $condition->condition($field_from, $this->argument, $op_right)->condition($field_to, $this->argument, $op_left));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSortName() {
    return $this->t('Numerical', [], ['context' => 'Sort order']);
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $operators = $this->operators();
    $operator = $this->options['operator'];
    return $operators[$operator]['short'];
  }

  /**
   * Define the operators supported for ranges.
   */
  protected function operators() {
    $operators = [
      'within' => [
        'short' => $this->t('contains'),
        'method' => 'opWithin',
      ],
      'not within' => [
        'short' => $this->t('does not contain'),
        'method' => 'opWithin',
      ],
    ];

    return $operators;
  }

}
