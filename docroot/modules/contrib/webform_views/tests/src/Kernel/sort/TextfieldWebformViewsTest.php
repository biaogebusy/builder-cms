<?php

namespace Drupal\Tests\webform_views\Kernel\sort;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test 'textfield' webform element as a views sort.
 */
#[Group('webform_views')]
#[RunTestsInSeparateProcesses]
class TextfieldWebformViewsTest extends WebformViewsSortTestBase {

  /**
   * {@inheritdoc}
   */
  protected static array $webform_elements = [
    'element' => [
      '#type' => 'textfield',
      '#title' => 'Textfield',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected static array $webform_submissions_data = [
    ['element' => 'Submission 1'],
    ['element' => 'Submission 2'],
  ];

  /**
   * {@inheritdoc}
   */
  protected static array $view_handlers = [
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
