<?php

namespace Drupal\Tests\webform_views\Kernel\argument;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test 'textarea' webform element as a views argument.
 */
#[Group('webform_views')]
#[RunTestsInSeparateProcesses]
class TextareaWebformViewsTest extends WebformViewsArgumentTestBase {

  /**
   * {@inheritdoc}
   */
  protected static array $webform_elements = [
    'element' => [
      '#type' => 'textarea',
      '#title' => 'Text area',
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
