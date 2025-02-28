<?php

namespace Drupal\webform_views\Plugin\views\relationship;

use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Drupal\views\Plugin\ViewsHandlerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A relationship handlers which reverse entity references.
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("webform_views_entity_reverse")
 */
class WebformViewsEntityReverse extends RelationshipPluginBase {

  /**
   * WebformViewsEntityReverse constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\views\Plugin\ViewsHandlerManager $join_manager
   *   The views plugin join manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ViewsHandlerManager $join_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->joinManager = $join_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.views.join')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    // First, relate our base table to webform submission data row that contains
    // entity reference.
    $first = [
      'left_table' => $this->tableAlias,
      'left_field' => $this->definition['left_field'],
      'table' => 'webform_submission_data',
      'field' => 'value',
      'adjusted' => TRUE,
      'extra' => [
        [
          'field' => 'webform_id',
          'value' => $this->definition['webform'],
        ],
        [
          'field' => 'name',
          'value' => $this->definition['webform element'],
        ],
      ],
    ];
    if (!empty($this->options['required'])) {
      $first['type'] = 'INNER';
    }

    if (!empty($this->definition['join_extra'])) {
      $first['extra'] = array_merge($first['extra'], $this->definition['join_extra']);
    }

    if (!empty($this->definition['join_id'])) {
      $id = $this->definition['join_id'];
    }
    else {
      $id = 'standard';
    }
    $first_join = $this->joinManager->createInstance($id, $first);

    $this->first_alias = $this->query->addTable($first['table'], $this->relationship, $first_join);

    // Second, relate the field table to the entity specified using
    // the entity id on the field table and the entity's id field.
    $second = [
      'left_table' => $this->first_alias,
      'left_field' => $this->definition['base field'],
      'table' => $this->definition['base'],
      'field' => $this->definition['base field'],
      'adjusted' => TRUE,
    ];

    if (!empty($this->options['required'])) {
      $second['type'] = 'INNER';
    }

    if (!empty($this->definition['join_id'])) {
      $id = $this->definition['join_id'];
    }
    else {
      $id = 'standard';
    }
    $second_join = $this->joinManager->createInstance($id, $second);
    $second_join->adjusted = TRUE;

    // Use a short alias for this.
    $alias = $this->definition['id'] . '_' . $this->table;

    $this->alias = $this->query->addRelationship($alias, $second_join, $this->definition['base'], $this->relationship);
  }

}
