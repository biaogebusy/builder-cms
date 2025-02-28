<?php

namespace Drupal\Tests\state_machine\Kernel;

use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the TransitionEvent.
 *
 * @group state_machine
 */
class WorkflowTransitionEventTest extends KernelTestBase {

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
   * Tests the transition event.
   */
  public function testTransitionEvent() {
    $entity = EntityTestWithBundle::create([
      'type' => 'first',
      'name' => 'Test entity',
      'field_state' => 'new',
    ]);
    $entity->save();

    $entity->get('field_state')->first()->applyTransitionById('create');
    $entity->save();

    $messages = \Drupal::messenger()->all();
    $message = reset($messages);
    $this->assertCount(6, $message);
    $this->assertEquals('Test entity (field_state) - Fulfillment at pre-transition (workflow: default, transition: create).', (string) $message[0]);
    $this->assertEquals('Test entity (field_state) - Fulfillment at group pre-transition (workflow: default, transition: create).', (string) $message[1]);
    $this->assertEquals('Test entity (field_state) - Fulfillment at generic pre-transition (workflow: default, transition: create).', (string) $message[2]);
    $this->assertEquals('Test entity (field_state) - Fulfillment at post-transition (workflow: default, transition: create).', (string) $message[3]);
    $this->assertEquals('Test entity (field_state) - Fulfillment at group post-transition (workflow: default, transition: create).', (string) $message[4]);
    $this->assertEquals('Test entity (field_state) - Fulfillment at generic post-transition (workflow: default, transition: create).', (string) $message[5]);

    \Drupal::messenger()->deleteAll();
    $entity->get('field_state')->first()->applyTransitionById('fulfill');
    $entity->save();

    $messages = \Drupal::messenger()->all();
    $message = reset($messages);
    $this->assertCount(4, $message);
    $this->assertEquals('Test entity (field_state) - Completed at group pre-transition (workflow: default, transition: fulfill).', (string) $message[0]);
    $this->assertEquals('Test entity (field_state) - Completed at generic pre-transition (workflow: default, transition: fulfill).', (string) $message[1]);
    $this->assertEquals('Test entity (field_state) - Completed at group post-transition (workflow: default, transition: fulfill).', (string) $message[2]);
    $this->assertEquals('Test entity (field_state) - Completed at generic post-transition (workflow: default, transition: fulfill).', (string) $message[3]);

    \Drupal::messenger()->deleteAll();
    $entity = EntityTestWithBundle::create([
      'type' => 'first',
      'name' => 'Test entity 2',
      'field_state' => 'new',
    ]);
    $entity->save();
    $entity->get('field_state')->first()->applyTransitionById('create');
    $entity->save();

    $messages = \Drupal::messenger()->all();
    $message = reset($messages);
    $this->assertCount(6, $message);
    $this->assertEquals('Test entity 2 (field_state) - Fulfillment at pre-transition (workflow: default, transition: create).', (string) $message[0]);
    $this->assertEquals('Test entity 2 (field_state) - Fulfillment at group pre-transition (workflow: default, transition: create).', (string) $message[1]);
    $this->assertEquals('Test entity 2 (field_state) - Fulfillment at generic pre-transition (workflow: default, transition: create).', (string) $message[2]);
    $this->assertEquals('Test entity 2 (field_state) - Fulfillment at post-transition (workflow: default, transition: create).', (string) $message[3]);
    $this->assertEquals('Test entity 2 (field_state) - Fulfillment at group post-transition (workflow: default, transition: create).', (string) $message[4]);
    $this->assertEquals('Test entity 2 (field_state) - Fulfillment at generic post-transition (workflow: default, transition: create).', (string) $message[5]);

    \Drupal::messenger()->deleteAll();
    // Ensure manually setting the state to "create", triggers the cancel
    // transition and not the 'fulfill' transition previously applied.
    $entity->set('field_state', 'canceled');
    $entity->save();
    $messages = \Drupal::messenger()->all();
    $message = reset($messages);
    $this->assertCount(4, $message);
    $this->assertEquals('Test entity 2 (field_state) - Canceled at group pre-transition (workflow: default, transition: cancel).', (string) $message[0]);
    $this->assertEquals('Test entity 2 (field_state) - Canceled at generic pre-transition (workflow: default, transition: cancel).', (string) $message[1]);
    $this->assertEquals('Test entity 2 (field_state) - Canceled at group post-transition (workflow: default, transition: cancel).', (string) $message[2]);
    $this->assertEquals('Test entity 2 (field_state) - Canceled at generic post-transition (workflow: default, transition: cancel).', (string) $message[3]);
  }

  /**
   * Tests the transition event with two identical to and from states.
   */
  public function testTransitionEventTwoTransitions() {
    $entity = EntityTestWithBundle::create([
      'type' => 'third',
      'name' => 'Test entity',
      'field_state' => 'new',
    ]);
    $entity->save();

    // Test that applying the second transition also throws the second transition's event.
    $entity->get('field_state')->first()->applyTransitionById('complete2');
    $entity->save();

    $messages = \Drupal::messenger()->all();
    $message = reset($messages);
    $this->assertCount(4, $message);
    $this->assertEquals('Test entity (field_state) - Completed at group pre-transition (workflow: two_transitions, transition: complete2).', (string) $message[0]);
    $this->assertEquals('Test entity (field_state) - Completed at generic pre-transition (workflow: two_transitions, transition: complete2).', (string) $message[1]);
    $this->assertEquals('Test entity (field_state) - Completed at group post-transition (workflow: two_transitions, transition: complete2).', (string) $message[2]);
    $this->assertEquals('Test entity (field_state) - Completed at generic post-transition (workflow: two_transitions, transition: complete2).', (string) $message[3]);
  }

  /**
   * Tests the transition event when a state transition occurs to same value.
   */
  public function testTransitionEventSameState() {
    $entity = EntityTestWithBundle::create([
      'type' => 'fourth',
      'name' => 'Test entity',
      'field_state' => 'new',
    ]);
    $entity->save();

    // Test that explicitly specifying the transition to same fires event.
    $entity->get('field_state')->first()->applyTransitionById('same');
    $entity->save();
    $messages = \Drupal::messenger()->all();
    $this->assertCount(4, $messages['status']);
    \Drupal::messenger()->deleteAll();

    // Test that simply setting the value to same as existing does not dispatch.
    $entity->get('field_state')->first()->setValue('new');
    $entity->save();
    $this->assertEmpty(\Drupal::messenger()->all());
  }

}
