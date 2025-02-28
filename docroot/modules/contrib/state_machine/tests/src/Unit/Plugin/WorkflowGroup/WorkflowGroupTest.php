<?php

namespace Drupal\Tests\state_machine\Unit\Plugin\WorkflowGroup;

use Drupal\state_machine\Plugin\Workflow\Workflow;
use Drupal\state_machine\Plugin\WorkflowGroup\WorkflowGroup;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\state_machine\Plugin\WorkflowGroup\WorkflowGroup
 * @group state_machine
 */
class WorkflowGroupTest extends UnitTestCase {

  /**
   * The workflow group.
   *
   * @var \Drupal\state_machine\Plugin\WorkflowGroup\WorkflowGroup
   */
  protected $workflowGroup;

  /**
   * The plugin definition array.
   *
   * @var array
   */
  protected $definition = [
    'id' => 'entity_test',
    'label' => 'Entity Test',
    'entity_type' => 'entity_test',
    'class' => WorkflowGroup::class,
    'workflow_class' => Workflow::class,
    'provider' => 'state_machine_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->workflowGroup = new WorkflowGroup([], 'order', $this->definition);
  }

  /**
   * @covers ::getId
   */
  public function testGetId() {
    $this->assertEquals($this->definition['id'], $this->workflowGroup->getId());
  }

  /**
   * @covers ::getLabel
   */
  public function testGetLabel() {
    $this->assertEquals($this->definition['label'], $this->workflowGroup->getLabel(), 'Workflow group label matches the expected one');
  }

  /**
   * @covers ::getEntityTypeId
   */
  public function testGetEntityTypeId() {
    $this->assertEquals($this->definition['entity_type'], $this->workflowGroup->getEntityTypeId(), 'Workflow group entity type id matches the expected one');
  }

  /**
   * @covers ::getWorkflowClass
   */
  public function testGetWorkflowClass() {
    $this->assertEquals($this->definition['workflow_class'], $this->workflowGroup->getWorkflowClass(), 'Workflow group class matches the expected one');
  }

}
