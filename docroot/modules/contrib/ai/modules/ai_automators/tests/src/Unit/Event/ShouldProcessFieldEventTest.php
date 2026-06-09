<?php

namespace Drupal\Tests\ai_automators\Unit\Event;

use Drupal\ai_automators\Event\ShouldProcessFieldEvent;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests ShouldProcessFieldEvent behavior.
 *
 * @group ai_automators
 */
class ShouldProcessFieldEventTest extends UnitTestCase {

  /**
   * Tests constructor values are available through getters.
   */
  public function testConstructorContextGetters() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $automatorConfig = [
      'field_name' => 'field_summary',
      'rule' => 'summary_rule',
    ];

    $event = new ShouldProcessFieldEvent($entity, $fieldDefinition, $automatorConfig, TRUE);

    $this->assertSame($entity, $event->getEntity());
    $this->assertSame($fieldDefinition, $event->getFieldDefinition());
    $this->assertSame($automatorConfig, $event->getAutomatorConfig());
    $this->assertTrue($event->shouldProcess());
  }

  /**
   * Tests process decision can be changed by subscribers.
   */
  public function testSetShouldProcess() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $automatorConfig = [
      'field_name' => 'field_summary',
      'rule' => 'summary_rule',
    ];

    $event = new ShouldProcessFieldEvent($entity, $fieldDefinition, $automatorConfig, FALSE);
    $this->assertFalse($event->shouldProcess());

    $event->setShouldProcess(TRUE);
    $this->assertTrue($event->shouldProcess());
  }

}
