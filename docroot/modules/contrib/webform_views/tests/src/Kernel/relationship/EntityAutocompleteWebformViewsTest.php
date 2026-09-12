<?php

namespace Drupal\Tests\webform_views\Kernel\relationship;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test relationship of 'entity_autocomplete' webform element.
 */
#[Group('webform_views')]
#[RunTestsInSeparateProcesses]
class EntityAutocompleteWebformViewsTest extends WebformViewsRelationshipTestBase {

  /**
   * {@inheritdoc}
   */
  protected $target_entity_type = 'user';

  /**
   * {@inheritdoc}
   */
  protected static array $webform_elements = [
    'element' => [
      '#type' => 'entity_autocomplete',
      '#title' => 'Entity Autocomplete',
      '#target_type' => 'user',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected static array $webform_submissions_data = [
    ['element' => 1],
  ];

  /**
   * {@inheritdoc}
   */
  protected static array $view_handlers = [
    'relationship' => [[
      'id' => 'element',
      'table' => 'webform_submission_field_webform_element',
      'field' => 'webform_submission_value',
      'options' => [],
    ]],
    'field' => [[
      'id' => 'entity_id',
      'table' => 'users_field_data',
      'field' => 'uid',
      'options' => [
        'relationship' => 'element',
        'alter' => [],
        'empty' => '',
        'hide_empty' => FALSE,
        'empty_zero' => FALSE,
        'hide_alter_empty' => TRUE,
      ],
    ]],
  ];

}
