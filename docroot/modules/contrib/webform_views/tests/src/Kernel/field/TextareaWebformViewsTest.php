<?php

namespace Drupal\Tests\webform_views\Kernel\field;

/**
 * Test 'textarea' webform element as a views field.
 *
 * @group webform_views_textarea
 */
class TextareaWebformViewsTest extends WebformViewsFieldTestBase {

  protected $webform_elements = [
    'element' => [
      '#type' => 'textarea',
      '#title' => 'Text area',
    ],
  ];

  protected $webform_submissions_data = [
    ['element' => 'Submission 1'],
    ['element' => 'Submission 2'],
  ];

  protected $webform_submission_multivalue_data = [
    ['element' => ['Submission 1.1', 'Submission 1.2']],
    ['element' => ['Submission 2.1', 'Submission 2.2']],
  ];

  protected $view_handlers = [
    'field' => [[
      'id' => 'element',
      'table' => 'webform_submission_field_webform_element',
      'field' => 'webform_submission_value',
      'options' => [],
    ]],
  ];

}
