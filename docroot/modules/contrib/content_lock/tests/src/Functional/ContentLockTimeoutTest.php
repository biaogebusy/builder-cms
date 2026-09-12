<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\block_content\BlockContentInterface;
use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\content_lock_test\ReleaseRecorder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test content_lock timeout functionality.
 *
 * @group content_lock
 */
#[RunTestsInSeparateProcesses]
class ContentLockTimeoutTest extends BrowserTestBase {

  use TaxonomyTestTrait;
  use CronRunTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'language',
    'user',
    'node',
    'field',
    'field_ui',
    'taxonomy',
    'block',
    'block_content',
    'content_lock',
    'content_lock_test',
    'content_lock_timeout_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Array standard permissions for normal user.
   *
   * @var string[]
   */
  protected array $permissions1;

  /**
   * Array standard permissions for user2.
   *
   * @var string[]
   */
  protected array $permissions2;

  /**
   * User with permission to administer entities.
   */
  protected UserInterface $adminUser;

  /**
   * Standard User.
   */
  protected UserInterface $user1;

  /**
   * Standard User.
   */
  protected UserInterface $user2;

  /**
   * A node created.
   */
  protected NodeInterface $article1;

  /**
   * A vocabulary created.
   */
  protected VocabularyInterface $vocabulary;

  /**
   * A term created.
   */
  protected TermInterface $term1;

  /**
   * A Block created.
   */
  protected BlockContentInterface $block1;

  /**
   * Lock service.
   */
  protected ContentLockInterface $lockService;

  /**
   * Setup and Rebuild node access.
   */
  public function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article']);

    $this->adminUser = $this->drupalCreateUser([
      'edit any article content',
      'delete any article content',
      'administer nodes',
      'administer content types',
      'administer users',
      'administer blocks',
      'administer taxonomy',
      'administer content lock',
    ]);

    $this->permissions1 = [
      'create article content',
      'edit any article content',
      'delete any article content',
      'access content',
      'administer blocks',
      'administer taxonomy',
    ];

    $this->permissions2 = [
      'create article content',
      'edit any article content',
      'delete any article content',
      'access content',
      'administer blocks',
      'administer taxonomy',
      'break content lock',
    ];

    // Create articles nodes.
    $this->article1 = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Article 1',
    ]);

    // Create vocabulary and terms.
    $this->vocabulary = $this->createVocabulary();
    $this->term1 = $this->createTerm($this->vocabulary);

    $this->user1 = $this->drupalCreateUser($this->permissions1);
    $this->user2 = $this->drupalCreateUser($this->permissions2);

    $this->cronRun();

    $this->drupalLogin($this->adminUser);
    $edit = [
      // Set timeout to 10 minutes.
      'timeout' => 10,
    ];
    $this->drupalGet('/admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');
    // Refresh the container so the lock service is updated with the new config.
    $this->rebuildAll();

    $this->lockService = \Drupal::service('content_lock');
  }

  /**
   * Test content lock timeout with nodes.
   */
  public function testContentLockNode(): void {
    // We protect the bundle created.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'node[bundles][article]' => 1,
    ];
    $this->drupalGet('/admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    $this->doTestForEntity($this->article1);
  }

  /**
   * Test content lock timeout with terms.
   */
  public function testContentLockTerm(): void {
    // We protect the bundle created.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'taxonomy_term[bundles][' . $this->term1->bundle() . ']' => 1,
    ];
    $this->drupalGet('/admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    $this->doTestForEntity($this->term1);
  }

  /**
   * Run the same tests for node, block and term.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to tests.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function doTestForEntity(EntityInterface $entity): void {
    // We lock article1.
    $this->drupalLogin($this->user2);

    $this->lockContentByUser1($entity);

    // Content should be locked.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->assertSession()->pageTextContains("This content is being edited by the user {$this->user1->getDisplayName()} and is therefore locked to prevent changes by other users.");

    // Jump 9 minutes into the future and run cron. The lock should not be
    // released.
    \Drupal::time()->setTimePatch(9 * 60);
    $this->cronRun();
    $this->assertLockOnContent($entity);
    $this->assertEmpty(\Drupal::service(ReleaseRecorder::class)->getReleasedEntities());

    // Jump into future to release lock.
    \Drupal::time()->setTimePatch(11 * 60);
    $this->cronRun();
    $released_entities = \Drupal::service(ReleaseRecorder::class)->getReleasedEntities();
    $this->assertCount(1, $released_entities);
    $this->assertSame([
      'entity_id' => $entity->id(),
      'langcode' => 'und',
      'form_op' => '*',
      'entity_type' => $entity->getEntityTypeId(),
    ], $released_entities[0]);
    \Drupal::time()->setTimePatch(0);

    // Content should be unlocked by cron.
    $this->assertNoLockOnContent($entity);
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->assertSession()->pageTextContains('This content is now locked against simultaneous editing.');
    $this->assertLockOnContent($entity);

    // Content should be unlocked when the last session of a user is closed by
    // logging out.
    $this->assertEmpty(\Drupal::service(ReleaseRecorder::class)->getReleasedEntities());
    $this->drupalLogout();
    $released_entities = \Drupal::service(ReleaseRecorder::class)->getReleasedEntities();
    $this->assertCount(1, $released_entities);
    $this->assertSame([
      'entity_id' => $entity->id(),
      'langcode' => 'und',
      'form_op' => '*',
      'entity_type' => $entity->getEntityTypeId(),
    ], $released_entities[0]);

    // There should be no lock on the content after logout.
    $this->assertNoLockOnContent($entity);

    $this->lockContentByUser1($entity);

    $this->drupalLogin($this->user2);

    // Jump 9 minutes into the future and the content should still be locked.
    \Drupal::time()->setTimePatch(9 * 60);
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->assertSession()->pageTextContains("This content is being edited by the user {$this->user1->getDisplayName()} and is therefore locked to prevent changes by other users.");

    // Jump into the future.
    \Drupal::time()->setTimePatch(11 * 60);
    $this->assertStaleLockOnContent($entity);
    $this->assertEmpty(\Drupal::service(ReleaseRecorder::class)->getReleasedEntities());
    // Lock should be released.
    $this->drupalGet($entity->toUrl('edit-form'));
    $released_entities = \Drupal::service(ReleaseRecorder::class)->getReleasedEntities();
    $this->assertCount(1, $released_entities);
    $this->assertSame([
      'entity_id' => $entity->id(),
      'langcode' => 'und',
      'form_op' => '*',
      'entity_type' => $entity->getEntityTypeId(),
    ], $released_entities[0]);
    $this->assertSession()->pageTextContains('This content is now locked by you against simultaneous editing.');
  }

  /**
   * Create lock from user 1.
   *
   * As logout is removing locks, it is only possible to set a lock from another
   * user with the lock service.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which should be locked.
   */
  protected function lockContentByUser1(EntityInterface $entity): void {
    $this->lockService->releaseAllUserLocks((int) $this->user2->id());
    // Remove any recorded releases.
    \Drupal::service(ReleaseRecorder::class)->getReleasedEntities();
    $this->lockService->locking($entity, 'edit', (int) $this->user1->id());
    $lock = $this->lockService->fetchLock($entity, 'edit');
    $this->assertNotNull($lock, 'Lock present');
    $this->assertEquals($this->user1->label(), $lock->name, 'Lock present for correct user.');
  }

  /**
   * Assert if no lock is present for content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which should not have a lock.
   */
  protected function assertNoLockOnContent(EntityInterface $entity): void {
    $lock = $this->lockService->fetchLock($entity, 'edit', TRUE);
    $this->assertFalse($lock, 'No lock present.');
  }

  /**
   * Assert if lock is present for content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which should not have a lock.
   */
  protected function assertLockOnContent(EntityInterface $entity): void {
    $lock = $this->lockService->fetchLock($entity, 'edit');
    $this->assertInstanceOf(\StdClass::class, $lock, 'Lock present.');
  }

  /**
   * Assert if stale lock is present for content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which should not have a lock.
   */
  protected function assertStaleLockOnContent(EntityInterface $entity): void {
    $lock = $this->lockService->fetchLock($entity, 'edit');
    $this->assertFalse($lock, 'No lock present.');
    $lock = $this->lockService->fetchLock($entity, 'edit', include_stale_locks: TRUE);
    $this->assertInstanceOf(\StdClass::class, $lock, 'Stale lock present.');
  }

}
