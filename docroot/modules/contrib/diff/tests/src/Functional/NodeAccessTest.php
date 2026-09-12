<?php

declare(strict_types=1);

namespace Drupal\Tests\diff\Functional;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\node\NodeAccessRebuild;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the diff overview form with modules implementing node access.
 */
#[Group('diff')]
#[RunTestsInSeparateProcesses]
class NodeAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['diff', 'node', 'node_access_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType(['type' => 'article']);

    // Dummy user 1.
    $this->createUser();

    // @phpstan-ignore-next-line
    DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.4.0', static fn() => \Drupal::service(NodeAccessRebuild::class)->rebuild(), static fn() => \node_access_rebuild());
  }

  /**
   * Tests that the revision overview form still works with node access.
   */
  public function testOverview(): void {
    // Create an unpublished node with 3 revisions.
    $node = $this->createNode([
      'type' => 'article',
      'status' => FALSE,
    ]);
    $node->setTitle($this->randomString());
    $node->setNewRevision();
    $node->save();
    $node->setTitle($this->randomString());
    $node->setNewRevision();
    $node->save();
    $user = $this->createUser(['access content', 'view all revisions']);
    $this->drupalLogin($user);

    // Grant access via node_access_test.
    // @see node_access_test_node_access
    \Drupal::state()->set('node_access_test.allow_uid', $user->id());

    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet($node->toUrl('version-history'));
    $this->assertSession()->statusCodeEquals(200);

    // There should be 3 diff rows.
    $rows = $this->xpath('//tbody/tr');
    $this->assertCount(3, $rows, 'Did not find 3 diff rows.');

    // Compare selected revisions should not time out.
    $this->drupalGet('/node/' . $node->id() . '/revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->statusCodeEquals(200);
  }

}
