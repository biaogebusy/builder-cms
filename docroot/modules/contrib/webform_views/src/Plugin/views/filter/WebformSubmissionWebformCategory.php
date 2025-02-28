<?php

namespace Drupal\webform_views\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\filter\StringFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter for webform category of a webform submission.
 *
 * @ViewsFilter("webform_views_webform_category")
 */
class WebformSubmissionWebformCategory extends StringFilter {

  /**
   * Entity type manager service.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $instance->setEntityTypeManager($container->get('entity_type.manager'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function operators() {
    $operators = parent::operators();

    unset($operators['word']);
    unset($operators['allwords']);
    unset($operators['not_starts']);
    unset($operators['not_ends']);
    unset($operators['not']);
    unset($operators['shorterthan']);
    unset($operators['longerthan']);
    unset($operators['regular_expression']);

    $operators['=']['webform_operator'] = '=';
    $operators['!=']['webform_operator'] = '<>';
    $operators['contains']['webform_operator'] = 'CONTAINS';
    $operators['starts']['webform_operator'] = 'STARTS_WITH';
    $operators['ends']['webform_operator'] = 'ENDS_WITH';

    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
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
   * Setter for entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service to inject.
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
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
    $query->condition('category', $this->value, $operator['webform_operator']);
    return array_values($query->execute());
  }

}
