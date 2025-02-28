<?php

namespace Drupal\Tests\select_or_other\Unit;

use Drupal\Core\Form\FormState;
use Drupal\select_or_other\Element\ElementBase;

/**
 * Tests the form element implementation.
 *
 * @group select_or_other
 *
 * @covers \Drupal\select_or_other\Element\ElementBase
 */
class ElementsTest extends UnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getTestedClassName() {
    return 'Drupal\select_or_other\Plugin\Element\ElementBase';
  }

  /**
   * Tests the addition of the other option to an options array.
   */
  public function testAddOtherOption() {
    $options = [];

    // Make the protected method accessible and invoke it.
    $method = new \ReflectionMethod('Drupal\select_or_other\Element\ElementBase', 'addOtherOption');
    $method->setAccessible(TRUE);
    $options = $method->invoke(NULL, $options);

    $this->assertEquals(['select_or_other' => '- Other -'], $options);
  }

  /**
   * Tests the value callback.
   */
  public function testValueCallback() {
    $form_state = new FormState();
    $element = [
      '#multiple' => FALSE,
    ];
    $input = [
      'select' => 'Selected text',
      'other' => 'Other text',
    ];

    $expected = [$input['select']];
    $values = ElementBase::valueCallback($element, $input, $form_state);
    $this->assertEquals($expected, $values, 'Returned single value select.');

    $input['select'] = 'select_or_other';
    $expected = [$input['other']];
    $values = ElementBase::valueCallback($element, $input, $form_state);
    $this->assertEquals($expected, $values, 'Returned single value other.');

    $element['#multiple'] = TRUE;
    $input['select'] = ['Selected text'];
    $expected = ['select' => $input['select'], 'other' => []];
    $values = ElementBase::valueCallback($element, $input, $form_state);
    $this->assertEquals($expected, $values, 'Returned select array and empty other array.');

    $input['select'][] = 'select_or_other';
    $expected['other'] = [$input['other']];
    $values = ElementBase::valueCallback($element, $input, $form_state);
    $this->assertEquals($expected, $values, 'Returned select array and other array.');

    $input['select'] = ['select_or_other'];
    $expected = ['select' => [], 'other' => [$input['other']]];
    $values = ElementBase::valueCallback($element, $input, $form_state);
    $this->assertEquals($expected, $values, 'Returned empty select and other array.');

    $input['select'][] = 'Selected';
    $element['#merged_values'] = TRUE;
    $expected = ['Selected', $input['other']];
    $values = ElementBase::valueCallback($element, $input, $form_state);
    $this->assertEquals($expected, $values, 'Returned merged array.');

    $input['select'] = ['Selected'];
    $input['other'] = '';
    $expected = ['Selected'];
    $values = ElementBase::valueCallback($element, $input, $form_state);
    $this->assertEquals($expected, $values, 'Returned merged array.');

    foreach ([TRUE, FALSE] as $multiple) {
      $element['#multiple'] = $multiple;
      $input = ['other' => 'Other value'];
      $expected = [];
      $values = ElementBase::valueCallback($element, $input, $form_state);
      $this->assertEquals($expected, $values, 'Submitting only the other value results in an empty array.');
    }

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
    $element = [
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

    $expected_element = $element + [
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

    $resulting_element = ElementBase::processSelectOrOther($element, $form_state, $form);
    $this->assertEquals($expected_element, $resulting_element);
    $this->assertEquals($resulting_element, $element);

  }

  /**
   * Tests the processing of form API #state .
   */
  public function testPrepareState() {
    // Test ElementBase.
    // Make the protected method accessible and invoke it.
    $method = new \ReflectionMethod('Drupal\select_or_other\Element\ElementBase', 'prepareState');
    $method->setAccessible(TRUE);

    $result = $method->invoke(NULL, 'state', 'name', 'key', 'value');
    $expected = [
      'state' => [
        ':input[name="name"]' => ['key' => 'value'],
      ],
    ];

    $this->assertEquals($expected, $result);
  }

}
