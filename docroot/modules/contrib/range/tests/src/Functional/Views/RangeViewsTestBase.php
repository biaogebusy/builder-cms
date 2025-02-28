<?php

namespace Drupal\Tests\range\Functional\Views;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\views\Functional\ViewTestBase;

/**
 * Base class for testing range handlers.
 */
abstract class RangeViewsTestBase extends ViewTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['range_test', 'node', 'range'];

  /**
   * Entity type ID we are testing against.
   *
   * @var string
   */
  protected $entityTypeId = 'node';

  /**
   * Bundle we are testing against.
   *
   * @var string
   */
  protected $bundle = 'test_bundle';

  /**
   * Name of the field we are testing against.
   *
   * Note, this is used in the default test view.
   *
   * @var string
   */
  protected $fieldName = 'field_range_integer';

  /**
   * Nodes to test.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes = [];

  /**
   * Columns map.
   *
   * @var string[]
   */
  protected $map = [
    'nid' => 'nid',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['range_test']): void {
    parent::setUp($import_test_views, $modules);

    // Add a date field to page nodes.
    $node_type = NodeType::create([
      'type' => $this->bundle,
      'name' => $this->bundle,
    ]);
    $node_type->save();
    $fieldStorage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => $this->entityTypeId,
      'type' => 'range_integer',
    ]);
    $fieldStorage->save();
    $field = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => $this->bundle,
      'required' => TRUE,
    ]);
    $field->save();

    // Views needs to be aware of the new field.
    $this->container->get('views.views_data')->clear();
  }

}
