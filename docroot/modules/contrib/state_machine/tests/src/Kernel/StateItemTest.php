<?php

namespace Drupal\Tests\state_machine\Kernel;

use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;

/**
 * @coversDefaultClass \Drupal\state_machine\Plugin\Field\FieldType\StateItem
 * @group state_machine
 */
class StateItemTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'user',
    'state_machine',
    'state_machine_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test_with_bundle');
    $this->installConfig(['state_machine_test']);
  }

  /**
   * @covers ::applyTransitionById
   */
  public function testInvalidTransitionApply() {
    $entity = EntityTestWithBundle::create([
      'name' => 'first',
      'type' => 'first',
    ]);
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $state_item */
    $state_item = $entity->get('field_state')->first();
    $this->expectException(\InvalidArgumentException::class);
    $state_item->applyTransitionById('INVALID');
  }

  /**
   * @dataProvider providerTestField
   */
  public function testField($initial_state, $allowed_transitions, $invalid_new_state, $valid_transition, $expected_new_state) {
    $entity = EntityTestWithBundle::create([
      'name' => 'second',
      'type' => 'second',
      'field_state' => $initial_state,
    ]);
    $this->assertEquals($initial_state, $entity->get('field_state')->value);

    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $state_item */
    $state_item = $entity->get('field_state')->first();
    // Confirm that the transitions are correct.
    $transitions = $state_item->getTransitions();
    $this->assertCount(count($allowed_transitions), $transitions);
    $this->assertEquals($allowed_transitions, array_keys($transitions));
    if (count($allowed_transitions) > 0) {
      foreach ($allowed_transitions as $transition_id) {
        $this->assertTrue($state_item->isTransitionAllowed($transition_id));
      }
    }
    $this->assertFalse($state_item->isTransitionAllowed('foo'));
    // Confirm that invalid states are recognized.
    if ($invalid_new_state) {
      $state_item->value = $invalid_new_state;
      $this->assertEquals($initial_state, $state_item->getOriginalId());
      $this->assertEquals($invalid_new_state, $state_item->getId());
      $this->assertFalse($state_item->isValid());
    }

    // Revert to the initial state because the valid transaction could be
    // invalid from the invalid_new_state.
    $state_item->value = $initial_state;

    // Retrieve all workflow transitions.
    $workflow = $state_item->getWorkflow();
    $all_transitions = $workflow->getTransitions();
    // Pick a random invalid transition and assert it throws an Exception.
    $invalid_transitions = array_diff_key($all_transitions, $transitions);
    if ($invalid_transitions) {
      $random_key = array_rand($invalid_transitions);
      $this->expectException(\InvalidArgumentException::class);
      $state_item->applyTransition($invalid_transitions[$random_key]);
      // Also try applying by ID.
      $this->expectException(\InvalidArgumentException::class);
      $state_item->applyTransitionById($random_key);
    }

    $state_item->applyTransitionById($valid_transition);
    $this->assertEquals($initial_state, $state_item->getOriginalId());
    $this->assertEquals($expected_new_state, $state_item->getId());
    $this->assertTrue($state_item->isValid());
  }

  /**
   * Data provider for ::testField.
   *
   * @return array
   *   A list of testField function arguments.
   */
  public function providerTestField() {
    $data = [];
    $data['new->validation'] = ['new', ['create', 'cancel'], 'fulfillment', 'create', 'validation'];
    $data['new->canceled'] = ['new', ['create', 'cancel'], 'completed', 'cancel', 'canceled'];
    // The workflow defines validation->fulfillment and validation->canceled
    // transitions, but the second one is forbidden by the GenericGuard.
    $data['validation->fulfillment'] = ['validation', ['validate'], 'completed', 'validate', 'fulfillment'];
    // The workflow defines fulfillment->completed and fulfillment->canceled
    // transitions, but the second one is forbidden by the FulfillmentGuard.
    $data['fulfillment->completed'] = ['fulfillment', ['fulfill'], 'new', 'fulfill', 'completed'];

    return $data;
  }

  /**
   * @dataProvider providerSettableOptions
   */
  public function testSettableOptions($initial_state, $available_options) {
    $entity = EntityTestWithBundle::create([
      'name' => 'second',
      'type' => 'second',
      'field_state' => $initial_state,
    ]);
    $this->assertEquals($initial_state, $entity->get('field_state')->value);
    // An invalid state should not have any settable options.
    $this->assertEquals($available_options, $entity->get('field_state')->first()->getSettableOptions());
  }

  /**
   * Data provider for ::providerSettableOptions.
   *
   * @return array
   *   A list of providerSettableOptions function arguments.
   */
  public function providerSettableOptions() {
    $data = [];
    $data['new'] = ['new', ['canceled' => 'Canceled', 'validation' => 'Validation', 'new' => 'New']];
    $data['invalid'] = ['invalid', []];

    return $data;
  }

  /**
   * @covers ::generateSampleValue
   */
  public function testGenerateSampleValue() {
    $entity = EntityTestWithBundle::create([
      'name' => 'first',
      'type' => 'first',
      'field_state' => NULL,
    ]);
    $entity->field_state->generateSampleItems();
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $state_item */
    $state_item = $entity->get('field_state')->first();
    $this->assertEquals('default', $state_item->getWorkflow()->getId());
    $this->assertNotEmpty($state_item->getId());
    $this->assertTrue(in_array($state_item->getId(), array_keys($state_item->getWorkflow()->getStates())));
    $this->entityValidateAndSave($entity);

    $entity = EntityTestWithBundle::create([
      'name' => 'second',
      'type' => 'second',
      'field_state' => NULL,
    ]);
    $entity->field_state->generateSampleItems();
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $state_item */
    $state_item = $entity->get('field_state')->first();
    $this->assertNotEmpty($state_item->getId());
    $this->assertTrue(in_array($state_item->getId(), array_keys($state_item->getWorkflow()->getStates())));
    $this->entityValidateAndSave($entity);

    $entity = EntityTestWithBundle::create([
      'name' => 'third',
      'type' => 'third',
      'field_state' => NULL,
    ]);
    $entity->field_state->generateSampleItems();
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $state_item */
    $state_item = $entity->get('field_state')->first();
    $this->assertEquals('two_transitions', $state_item->getWorkflow()->getId());
    $this->assertNotEmpty($state_item->getId());
    $this->assertTrue(in_array($state_item->getId(), array_keys($state_item->getWorkflow()->getStates())));
    $this->entityValidateAndSave($entity);
  }

}
