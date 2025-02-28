<?php

namespace Drupal\Tests\webform_views\Kernel\argument;

/**
 * Test 'textfield' webform element as a views argument.
 *
 * @group webform_views_textfield
 */
class TextfieldWebformViewsTest extends WebformViewsArgumentTestBase {

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
    'argument' => [[
      'id' => 'element',
      'table' => 'webform_submission_field_webform_element',
      'field' => 'webform_submission_value',
      'options' => [
        'default_action' => 'not found',
      ],
    ]],
  ];

}
