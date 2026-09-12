<?php

namespace Drupal\shs_media\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\shs\Plugin\views\filter\ShsTaxonomyIndexTid;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter handler for taxonomy terms with depth.
 *
 * This handler is part of the media table and uses a subquery to find media
 * entities with the selected taxonomy terms.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("shs_media_taxonomy_index_tid_depth")
 */
class ShsMediaTaxonomyIndexTidDepth extends ShsTaxonomyIndexTid {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructor for the class.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\taxonomy\VocabularyStorageInterface $vocabulary_storage
   *   The vocabulary storage.
   * @param \Drupal\taxonomy\TermStorageInterface $term_storage
   *   The term storage.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountInterface|null $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, VocabularyStorageInterface $vocabulary_storage, TermStorageInterface $term_storage, Connection $database, ?AccountInterface $current_user = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $vocabulary_storage, $term_storage, $current_user);

    $this->database = $database;
    $this->translationContext = 'shs_media:taxonomy_index_tid_depth';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('taxonomy_vocabulary'),
      $container->get('entity_type.manager')->getStorage('taxonomy_term'),
      $container->get('database'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['depth'] = ['default' => 0];
    $options['reference_field'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function operatorOptions($which = 'title') {
    return [
      'or' => $this->t('Is one of'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildExtraOptionsForm($form, $form_state);

    $form['type']['#options']['shs'] = $this->t('Simple hierarchical select');

    $form['reference_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reference field'),
      '#default_value' => $this->options['reference_field'],
      '#required' => TRUE,
      '#description' => $this->t('The machine name of the media entity field that references taxonomy terms.'),
    ];

    $form['depth'] = [
      '#type' => 'weight',
      '#title' => $this->t('Depth'),
      '#default_value' => $this->options['depth'],
      '#description' => $this->t('The depth will match media entities tagged with terms in the hierarchy. For example, if you have the term "fruit" and a child term "apple", with a depth of 1 or higher then filtering for the term "fruit" will get media entities that are tagged with "apple" as well as "fruit". If negative, the reverse is true; searching for "apple" will also pick up media entities tagged with "fruit" if depth is -1 or lower.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {

    // Get the DB table and reference column name from the reference field name.
    $refFieldName = $this->options['reference_field'] . '_target_id';
    $refTableName = 'media__' . $this->options['reference_field'];

    if (!is_array($this->value)) {
      $operator = '=';
    }
    elseif (count($this->value) == 0) {
      return;
    }
    elseif (count($this->value) == 1) {
      $this->value = current($this->value);
      $operator = '=';
    }
    else {
      $operator = 'IN';
    }

    // The normal use of ensureMyTable() here breaks Views.
    // So instead we trick the filter into using the alias of the base table.
    // See https://www.drupal.org/node/271833.
    // If a relationship is set, we must use the alias it provides.
    if (!empty($this->relationship)) {
      $this->tableAlias = $this->relationship;
    }
    // If no relationship, then use the alias of the base table.
    elseif (method_exists($this->query, 'ensureTable')) {
      $this->tableAlias = $this->query->ensureTable($this->view->storage->get('base_table'));
    }
    else {
      return;
    }

    // Now build the subqueries.
    $subquery = $this->database->select($refTableName, 'tn');
    $subquery->addField('tn', 'entity_id');
    $or_condition = new Condition('OR');
    $where = $or_condition->condition('tn.' . $refFieldName, $this->value, $operator);
    $last = "tn";

    if ($this->options['depth'] > 0) {
      $subquery->leftJoin('taxonomy_term__parent', 'tp', "tp.entity_id = tn." . $refFieldName);
      $last = "tp";
      foreach (range(1, abs($this->options['depth'])) as $count) {
        $subquery->leftJoin('taxonomy_term__parent', "tp$count", "$last.parent_target_id = tp$count.entity_id");
        $where->condition("tp$count.entity_id", $this->value, $operator);
        $last = "tp$count";
      }
    }
    elseif ($this->options['depth'] < 0) {
      foreach (range(1, abs($this->options['depth'])) as $count) {
        $subquery->leftJoin('taxonomy_term__parent', "tp$count", "$last.entity_id = tp$count.parent_target_id");
        $where->condition("tp$count.entity_id", $this->value, $operator);
        $last = "tp$count";
      }
    }

    $subquery->condition($where);
    if (method_exists($this->query, 'addWhere')) {
      $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $subquery, 'IN');
    }
    else {
      return;
    }
  }

}
