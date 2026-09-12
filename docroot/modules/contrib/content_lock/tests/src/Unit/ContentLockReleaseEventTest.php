<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Unit;

use Drupal\content_lock\Event\ContentLockReleaseEvent;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests ContentLockReleaseEvent.
 *
 * @group content_lock
 */
class ContentLockReleaseEventTest extends UnitTestCase {

  /**
   * Tests access to deprecated properties.
   *
   * @group legacy
   */
  public function testDeprecatedPropertyAccess(): void {
    $this->expectDeprecation('Accessing content lock release event information via ->entity_id, ->entity_type, ->langcode, or ->form_op is deprecated in content_lock:3.0.0 and removed in content_lock:4.0.0. Use ->entities instead. See https://www.drupal.org/project/content_lock/issues/3530760');
    $entity = $this->prophesize(EntityInterface::class);
    $entity->id()->willReturn('5');
    $entity->getEntityTypeId()->willReturn('node');
    $event = ContentLockReleaseEvent::createFromEntity($entity->reveal(), 'und', NULL);

    $this->assertSame('5', $event->entity_id);
    $this->assertSame('node', $event->entity_type);
    $this->assertSame('und', $event->langcode);
    $this->assertNull($event->form_op);
  }

}
