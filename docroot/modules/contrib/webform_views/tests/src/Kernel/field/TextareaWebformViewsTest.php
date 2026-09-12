<?php

namespace Drupal\Tests\webform_views\Kernel\field;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test 'textarea' webform element as a views field.
 */
#[Group('webform_views')]
#[RunTestsInSeparateProcesses]
class TextareaWebformViewsTest extends WebformViewsFieldTestBase {

  protected static array $webform_elements = [
    'element' => [
      '#type' => 'textarea',
      '#title' => 'Text area',
    ],
  ];

  protected static array $webform_submissions_data = [
    ['element' => 'Submission 1'],
    ['element' => 'Submission 2'],
  ];

  protected static array $webform_submission_multivalue_data = [
    ['element' => ['Submission 1.1', 'Submission 1.2']],
    ['element' => ['Submission 2.1', 'Submission 2.2']],
  ];

  protected static array $view_handlers = [
    'field' => [[
      'id' => 'element',
      'table' => 'webform_submission_field_webform_element',
      'field' => 'webform_submission_value',
      'options' => [],
    ]],
  ];

}
