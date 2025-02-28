<?php

namespace Drupal\webform_views\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views\ViewExecutable;
use Drupal\webform\WebformInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter for webform status of a webform submission.
 *
 * @ViewsFilter("webform_views_webform_status")
 */
class WebformSubmissionWebformStatus extends InOperator {

  /**
   * Entity type manager service.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * WebformSubmissionWebformStatus constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->valueOptions = [
      WebformInterface::STATUS_OPEN => $this->t('Open'),
      WebformInterface::STATUS_CLOSED => $this->t('Closed'),
      WebformInterface::STATUS_SCHEDULED => $this->t('Scheduled'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function operators() {
    $operators = parent::operators();

    $operators['in']['webform_operator'] = 'IN';
    $operators['not in']['webform_operator'] = 'NOT IN';

    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple() {
    $webform_ids = $this->getApplicableWebformIds();
    if (empty($webform_ids)) {
      // Since no webforms were matched. Put a condition that yields FALSE.
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
    }
    else {
      $this->ensureMyTable();
      $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $webform_ids, 'IN');
    }

  }

  /**
   * Get a list of webform IDs that satisfy filter criterion.
   *
   * @return string[]
   *   Array of webform IDs that satisfy filter criterion.
   */
  protected function getApplicableWebformIds() {
    $operator = $this->operators()[$this->operator];

    $query = $this->entityTypeManager->getStorage('webform')->getQuery();
    $query->condition('status', $this->value, $operator['webform_operator']);
    return array_values($query->execute());
  }

}
