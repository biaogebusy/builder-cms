<?php

namespace Drupal\Tests\webform_views\Kernel\sort;

/**
 * Test 'textfield' webform element as a views sort.
 *
 * @group webform_views_textfield
 */
class TextfieldWebformViewsTest extends WebformViewsSortTestBase {

  protected $webform_elements = [
    'element' => [
      '#type' => 'textfield',
      '#title' => 'Textfield',
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
