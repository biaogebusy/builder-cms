<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Queue\DatabaseQueue;
use Drupal\Core\Queue\QueueFactory;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_automators\Plugin\AiAutomatorProcess\QueueWorkerProcessor;

/**
 * Tests queue deduplication logic in QueueWorkerProcessor.
 *
 * Verifies that modify() does not add a new queue item when one for the same
 * entity/field combination is already pending, and that the "Re-queue on each
 * save" flag correctly overrides that behavior.
 *
 * @group ai_automators
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
class QueueWorkerProcessorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The processor under test.
   */
  private QueueWorkerProcessor $processor;

  /**
   * A DatabaseQueue instance used to assert queue state.
   */
  private DatabaseQueue $dbQueue;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $connection = $this->container->get('database');

    // Instantiate DatabaseQueue directly so the processor and assertions use
    // the same database-backed queue. QueueFactory may resolve to a
    // non-database backend in the test environment.
    $this->dbQueue = new DatabaseQueue('ai_automator_field_modifier', $connection);

    // Mock QueueFactory so the processor always writes to the same database
    // queue that isAlreadyQueued() reads from.
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->willReturn($this->dbQueue);

    $this->processor = new QueueWorkerProcessor($queueFactory, $connection);
  }

  /**
   * Returns a mock entity with the given type and ID.
   */
  private function mockEntity(string $type = 'node', int $id = 1): EntityInterface {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn($type);
    $entity->method('id')->willReturn($id);
    return $entity;
  }

  /**
   * Returns a mock field definition with the given field name.
   */
  private function mockField(string $name = 'body'): FieldDefinitionInterface {
    $field = $this->createMock(FieldDefinitionInterface::class);
    $field->method('getName')->willReturn($name);
    return $field;
  }

  /**
   * Returns the current number of items in the automator queue.
   */
  private function queueCount(): int {
    return (int) $this->dbQueue->numberOfItems();
  }

  /**
   * Tests that modify() adds a queue item when the queue is empty.
   */
  public function testCreatesQueueItem(): void {
    $this->processor->modify(
      $this->mockEntity(),
      $this->mockField(),
      ['field_name' => 'body'],
    );

    $this->assertSame(1, $this->queueCount());
  }

  /**
   * Tests that a second save does not add a duplicate item by default.
   */
  public function testSkipsDuplicateWhenAlreadyQueued(): void {
    $config = ['field_name' => 'body'];

    $this->processor->modify($this->mockEntity(), $this->mockField(), $config);
    $this->processor->modify($this->mockEntity(), $this->mockField(), $config);

    $this->assertSame(1, $this->queueCount());
  }

  /**
   * Tests that re-queueing is allowed when the flag is explicitly enabled.
   */
  public function testAllowsRequeueWhenFlagIsSet(): void {
    $config = ['field_name' => 'body', 'queue_allow_requeue' => TRUE];

    $this->processor->modify($this->mockEntity(), $this->mockField(), $config);
    $this->processor->modify($this->mockEntity(), $this->mockField(), $config);

    $this->assertSame(2, $this->queueCount());
  }

  /**
   * Tests that two different fields on the same entity both get queue items.
   */
  public function testDoesNotSkipDifferentField(): void {
    $entity = $this->mockEntity();

    $this->processor->modify($entity, $this->mockField('body'), ['field_name' => 'body']);
    $this->processor->modify($entity, $this->mockField('title'), ['field_name' => 'title']);

    $this->assertSame(2, $this->queueCount());
  }

  /**
   * Tests that the same field on two different entities both get queue items.
   */
  public function testDoesNotSkipDifferentEntity(): void {
    $field = $this->mockField();

    $this->processor->modify($this->mockEntity('node', 1), $field, ['field_name' => 'body']);
    $this->processor->modify($this->mockEntity('node', 2), $field, ['field_name' => 'body']);

    $this->assertSame(2, $this->queueCount());
  }

}
