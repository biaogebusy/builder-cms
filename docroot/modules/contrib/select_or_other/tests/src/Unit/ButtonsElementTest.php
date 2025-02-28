<?php

namespace Drupal\Tests\select_or_other\Unit;

use Drupal\Core\Form\FormState;
use Drupal\select_or_other\Element\Buttons;

/**
 * Tests the form element implementation.
 *
 * @group select_or_other
 *
 * @covers \Drupal\select_or_other\Element\Buttons
 */
class ButtonsElementTest extends UnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getTestedClassName() {
    return 'Drupal\select_or_other\Plugin\Element\Buttons';
  }

  /**
   * Tests the processing of a select or other element.
   */
  public function testProcessSelectOrOther() {
    // Test ElementBase.
    // Make the protected method accessible and invoke it.
    $method = new \ReflectionMethod('Drupal\select_or_other\Element\ElementBase', 'addOtherOption');
    $method->setAccessible(TRUE);

    $form_state = new FormState();
    $form = [];
    $original_element = $element = [
      '#name' => 'select_or_other',
      '#no_empty_option' => FALSE,
      '#default_value' => 'default',
      '#required' => TRUE,
      '#multiple' => FALSE,
      '#options' => [
        'first_option' => 'First option',
        'second_option' => "Second option",
      ],
      '#other_option' => [
        'first_other_option' => 'First other option',
        'second_other_option' => "Second other option",
      ],
    ];

    $base_expected_element = $expected_element = $element + [
      'select' => [
        '#default_value' => $element['#default_value'],
        '#required' => $element['#required'],
        '#multiple' => $element['#multiple'],
        '#options' => $method->invoke(NULL, $element['#options'], $element['#other_option']),
        '#attributes' => [
          'aria-label' => $element['#title'] ?? $element['#name'],
        ],
        '#weight' => 10,
      ],
      'other' => [
        '#type' => 'textfield',
        '#attributes' => [
          'aria-label' => isset($element['#title']) ? $element['#title'] . ' Other' : $element['#name'] . ' Other',
        ],
        '#weight' => 20,
      ],
    ];

    // Test single cardinality Buttons.
    $element = $original_element;
    $expected_element = array_merge_recursive($base_expected_element, [
      'select' => [
        '#type' => 'checkboxes',
      ],
      'other' => [
        '#states' => [
          'visible' => [
            ':input[name="' . $element['#name'] . '[select][select_or_other]"]' => ['checked' => TRUE],
          ],
        ],
      ],
    ]);
    $element['#multiple'] = $expected_element['#multiple'] = $expected_element['select']['#multiple'] = TRUE;
    $resulting_element = Buttons::processSelectOrOther($element, $form_state, $form);
    $this->assertEquals($expected_element, $resulting_element);
    $this->assertEquals($resulting_element, $element);

    // Test multiple cardinality Buttons.
    $element = $original_element;
    $expected_element = array_merge_recursive($base_expected_element, [
      'select' => [
        '#type' => 'radios',
        '#value' => [],
      ],
      'other' => [
        '#states' => [
          'visible' => [
            ':input[name="' . $element['#name'] . '[select]"]' => ['value' => 'select_or_other'],
          ],
        ],
      ],
    ]);
    $resulting_element = Buttons::processSelectOrOther($element, $form_state, $form);
    $this->assertEquals($expected_element, $resulting_element);
    $this->assertEquals($resulting_element, $element);
  }

  /**
   * Make sure radio buttons always have a correct default value.
   */
  public function testEnsureCorrectDefaultValue() {
    $element = [
      'select' => [
        '#type' => 'radios',
      ],
    ];
    $arguments = [& $element];
    $ensure_correct_default_value = new \ReflectionMethod('Drupal\select_or_other\Element\Buttons', 'ensureCorrectDefaultValue');
    $ensure_correct_default_value->setAccessible(TRUE);

    $expected = $element;
    $ensure_correct_default_value->invokeArgs(NULL, $arguments);
    $this->assertEquals($expected, $element);

    $element['select']['#default_value'] = 'non_array_default';
    $expected = $element;
    $ensure_correct_default_value->invokeArgs(NULL, $arguments);
    $this->assertEquals($expected, $element);

    $element['select']['#default_value'] = ['array_default'];
    $expected['select']['#default_value'] = 'array_default';
    $ensure_correct_default_value->invokeArgs(NULL, $arguments);
    $this->assertEquals($expected, $element);

    $expected['select']['#type'] = $element['select']['#type'] = 'checkboxes';
    $expected['select']['#default_value'] = $element['select']['#default_value'] = ['array_default'];
    $ensure_correct_default_value->invokeArgs(NULL, $arguments);
    $this->assertEquals($expected, $element);
  }

  /**
   * Make sure the empty option gets added when necessary.
   */
  public function testAddEmptyOption() {
    $element = [
      '#multiple' => TRUE,
      '#required' => TRUE,
      'select' => [
        '#options' => [],
        '#value' => [],
      ],
    ];
    $arguments = [& $element];
    $add_empty_option = new \ReflectionMethod('Drupal\select_or_other\Element\Buttons', 'addEmptyOption');
    $add_empty_option->setAccessible(TRUE);

    $expected = $element;
    $add_empty_option->invokeArgs(NULL, $arguments);
    $this->assertEquals($expected, $element);

    $expected['#multiple'] = $element['#multiple'] = FALSE;
    $add_empty_option->invokeArgs(NULL, $arguments);
    $this->assertEquals($expected, $element);

    $expected['#required'] = $element['#required'] = FALSE;
    $add_empty_option->invokeArgs(NULL, $arguments);
    $this->assertEquals($expected, $element);

    $expected['#default_value'] = $element['#default_value'] = [];
    $add_empty_option->invokeArgs(NULL, $arguments);
    $this->assertEquals($expected, $element);

    $expected['#default_value'] = $element['#default_value'] = ['test'];
    $expected['select']['#options'] = [
      '' => '- None -',
    ];
    $add_empty_option->invokeArgs(NULL, $arguments);
    $this->assertEquals($expected, $element);

    $expected['#default_value'] = $element['#default_value'] = 'test';
    $expected['select']['#options'] = [
      '' => '- None -',
    ];
    $add_empty_option->invokeArgs(NULL, $arguments);
    $this->assertEquals($expected, $element);

    $expected['#no_empty_option'] = $element['#no_empty_option'] = FALSE;
    $add_empty_option->invokeArgs(NULL, $arguments);
    $this->assertEquals($expected, $element);

    $element['#no_empty_option'] = TRUE;
    $expected = $element;
    $add_empty_option->invokeArgs(NULL, $arguments);
    $this->assertEquals($expected, $element);
  }

}
