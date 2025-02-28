<?php

namespace Drupal\Tests\webform_views\Kernel\sort;

/**
 * Test 'textarea' webform element as a views sort.
 *
 * @group webform_views_textarea
 */
class TextareaWebformViewsTest extends WebformViewsSortTestBase {

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

  protected $view_handlers = [
    'field' => [[
      'id' => 'element',
      'table' => 'webform_submission_field_webform_element',
      'field' => 'webform_submission_value',
      'options' => [],
    ]],
    'sort' => [[
      'id' => 'element',
      'table' => 'webform_submission_field_webform_element',
      'field' => 'webform_submission_value',
      'options' => [],
    ]],
  ];

}
