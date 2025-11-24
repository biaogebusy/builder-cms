<?php

namespace Drupal\Tests\webform_views\Kernel\relationship;

/**
 * Test relationship of 'entity_autocomplete' webform element.
 *
 * @group webform_views_entity_autocomplete
 */
class EntityAutocompleteWebformViewsTest extends WebformViewsRelationshipTestBase {

  /**
   * {@inheritdoc}
   */
  protected $target_entity_type = 'user';

  /**
   * {@inheritdoc}
   */
  protected $webform_submissions_data = [
    ['element' => 1],
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->webform_elements = [
      'element' => [
        '#type' => 'entity_autocomplete',
        '#title' => 'Entity Autocomplete',
        '#target_type' => $this->target_entity_type,
      ],
    ];

    /** @var \Drupal\Core\Entity\EntityTypeInterface $target_entity_type */
    $target_entity_type = $this->container->get('entity_type.manager')->getDefinition($this->target_entity_type);

    $this->view_handlers = [
      'relationship' => [[
        'id' => 'element',
        'table' => 'webform_submission_field_webform_element',
        'field' => 'webform_submission_value',
        'options' => [],
      ]],
      'field' => [[
        'id' => 'entity_id',
        'table' => $target_entity_type->getDataTable(),
        'field' => $target_entity_type->getKey('id'),
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

}
