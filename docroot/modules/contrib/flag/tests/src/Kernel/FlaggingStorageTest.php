<?php

namespace Drupal\Tests\flag\Kernel;

use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests for flagging storage.
 *
 * @group flag
 */
class FlaggingStorageTest extends FlagKernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * User to test with.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * Test flag.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flag;

  /**
   * Test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->account = $this->createUser();

    $this->flag = $this->createFlag('node', ['article']);

    // A node to test with.
    $this->createContentType(['type' => 'article']);
    $this->node = $this->createNode(['type' => 'article']);
  }

  /**
   * Test that cache reset is working.
   */
  public function testCacheReset() {
    // Flag the node on behalf of the user.
    $this->flagService->flag($this->flag, $this->node, $this->account);
    $this->assertTrue($this->flag->isFlagged($this->node, $this->account));

    // Unflag and verify that the internal caches have been reset.
    $this->flagService->unflag($this->flag, $this->node, $this->account);
    $this->assertFalse($this->flag->isFlagged($this->node, $this->account));
  }

}
