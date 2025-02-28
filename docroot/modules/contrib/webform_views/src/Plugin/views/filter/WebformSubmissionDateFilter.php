<?php

namespace Drupal\webform_views\Plugin\views\filter;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\views\Plugin\views\filter\Date;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter for date values of a webform submission.
 *
 * @ViewsFilter("webform_submission_date_filter")
 */
class WebformSubmissionDateFilter extends Date {

  /**
   * @var TimeInterface
   */
  protected $time;

  /**
   * @var DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('datetime.time'),
      $container->get('date.formatter')
    );
  }

  /**
   * WebformSubmissionFieldFilter constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TimeInterface $time, DateFormatterInterface $date_formatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->time = $time;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public function opSimple($field) {
    $value = intval(strtotime($this->value['value'], $this->time->getRequestTime()));
    $value = $this->dateFormatter->format($value, 'custom', 'Y-m-d');
    $this->query->addWhere($this->options['group'], $field, $value, $this->operator);
  }

  /**
   * {@inheritdoc}
   */
  public function opBetween($field) {
    $a = intval(strtotime($this->value['min'], $this->time->getRequestTime()));
    $a = $this->dateFormatter->format($a, 'custom', 'Y-m-d');

    $b = intval(strtotime($this->value['max'], $this->time->getRequestTime()));
    $b = $this->dateFormatter->format($b, 'custom', 'Y-m-d');

    $this->query->addWhere($this->options['group'], $field, [$a, $b], $this->operator);
  }

  /**
   * {@inheritdoc}
   */
  function operators() {
    $operators = parent::operators();
    unset($operators['regular_expression']);

    return $operators;
  }

}
