<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Kernel;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMulChanged;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests simultaneous edit on test entity.
 *
 * @group content_lock
 */
#[RunTestsInSeparateProcesses]
class ContentLockEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_lock',
    'content_lock_hooks_test',
    'entity_test',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test_mul_changed');
    $this->installEntitySchema('user');
    $this->installSchema('content_lock', 'content_lock');
    $this->installConfig('content_lock');
  }

  /**
   * Tests entities with content locking enabled and disabled.
   *
   * @covers \Drupal\content_lock\ContentLock\ContentLock::isLockable
   */
  public function testHookContentLockEntityLockable(): void {
    $this->installEntitySchema('entity_test');
    $entity1 = EntityTest::create([
      'name' => 'Entity for user without break permission',
    ]);
    $entity1->save();
    /** @var \Drupal\content_lock\ContentLock\ContentLock $lock_service */
    $lock_service = $this->container->get('content_lock');
    $this->assertFalse($lock_service->isLockable($entity1));
    /** @var \Drupal\Core\Entity\EntityListBuilderInterface $list_builder */
    $list_builder = $this->container->get('entity_type.manager')->getListBuilder('entity_test');
    if (floatval(\Drupal::VERSION) > 11.2) {
      $metadata = new CacheableMetadata();
      $list_builder->getOperations($entity1, $metadata);
      $this->assertContains('config:content_lock.settings', $metadata->getCacheTags());
      $this->assertSame(['user.permissions'], $metadata->getCacheContexts());
      $this->assertSame(-1, $metadata->getCacheMaxAge());
    }

    $this->config('content_lock.settings')->set('types.entity_test', ['*'])->save();
    $this->assertTrue($lock_service->isLockable($entity1));
    if (floatval(\Drupal::VERSION) > 11.2) {
      $metadata = new CacheableMetadata();
      $list_builder->getOperations($entity1, $metadata);
      $this->assertContains('config:content_lock.settings', $metadata->getCacheTags());
      $this->assertSame(['user'], $metadata->getCacheContexts());
      $this->assertSame(0, $metadata->getCacheMaxAge());
    }
  }

  /**
   * Tests deleting entities with content locks and form op locking enabled.
   *
   * @covers content_lock_entity_access
   */
  public function testContentLockEntityProgrammaticDelete(): void {
    $this->config('content_lock.settings')
      ->set('types.entity_test_mul_changed', ['*'])
      ->set('form_op_lock.entity_test_mul_changed.mode', ContentLockInterface::FORM_OP_MODE_ALLOWLIST)
      ->save();
    $entity1 = EntityTestMulChanged::create([
      'name' => 'Entity for user without break permission',
    ]);
    $entity1->save();
    /** @var \Drupal\content_lock\ContentLock\ContentLock $lock_service */
    $lock_service = $this->container->get('content_lock');
    $this->assertTrue($lock_service->locking($entity1, '*', 1, TRUE));
    $this->assertInstanceOf(\StdClass::class, $lock_service->fetchLock($entity1));

    // Deleting the entity will cause the lock to be released.
    $entity1->delete();

    $this->assertFalse($lock_service->fetchLock($entity1));
  }

  /**
   * Tests delete access.
   *
   * @covers content_lock_entity_access
   */
  public function testContentLockEntityDeleteAccess(): void {
    $this->config('content_lock.settings')
      ->set('types.entity_test_mul_changed', ['*'])
      ->save();
    $entity1 = EntityTestMulChanged::create([
      'name' => 'Entity for user without break permission',
    ]);
    $entity1->save();
    /** @var \Drupal\content_lock\ContentLock\ContentLock $lock_service */
    $lock_service = $this->container->get('content_lock');
    $this->assertTrue($lock_service->locking($entity1, '*', 1, TRUE));
    $this->assertInstanceOf(\StdClass::class, $lock_service->fetchLock($entity1));

    $access = $entity1->access('delete', return_as_object: TRUE);
    $this->assertTrue($access->isForbidden());
    $this->assertSame(0, $access->getCacheMaxAge());
    $this->assertSame(['user'], $access->getCacheContexts());
    $this->assertSame(['config:content_lock.settings'], $access->getCacheTags());

    // Remove the entity type from locking.
    $this->config('content_lock.settings')
      ->set('types.entity_test_mul_changed', [])
      ->save();
    $this->container
      ->get('entity_type.manager')
      ->getAccessControlHandler('entity_test_mul_changed')
      ->resetCache();
    $access = $entity1->access('delete', return_as_object: TRUE);
    $this->assertTrue($access->isNeutral());
    $this->assertSame(-1, $access->getCacheMaxAge());
    $this->assertSame(['user.permissions'], $access->getCacheContexts());
    $this->assertSame(['config:content_lock.settings'], $access->getCacheTags());
  }

}
