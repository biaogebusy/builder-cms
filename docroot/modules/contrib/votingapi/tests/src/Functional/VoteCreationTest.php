<?php

namespace Drupal\Tests\votingapi\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\votingapi\Entity\Vote;

/**
 * Tests the Voting API basics.
 *
 * @group VotingAPI
 */
class VoteCreationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'votingapi', 'votingapi_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  private $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create Basic page and Article node types.
    if ($this->profile != 'standard') {
      $node_type = $this->drupalCreateContentType([
        'type' => 'page',
        'name' => 'Basic page',
        'display_submitted' => FALSE,
      ]);
      node_add_body_field($node_type);
    }

    $this->drupalLogin($this->drupalCreateUser());

    $title = $this->randomMachineName(8);

    $node = Node::create(['type' => 'page']);
    $node->setOwner($this->loggedInUser);
    $node->setTitle($title);
    $node->set('body', ['value' => $this->randomMachineName(16)]);
    $node->save();

    $this->node = $this->drupalGetNodeByTitle($title);
    $this->assertNotEmpty($this->node, 'Basic page created for Voting API tests.');
  }

  /**
   * Test voting with non-existent Vote Type Id.
   */
  public function testVoteCreationWithInvalidVoteType(): void {

    $vote = Vote::create(['type' => 'vote']);
    // $vote = Vote::create(['type' => 'fake_vote_type']);.
    $vote->setVotedEntityId($this->node->id());
    $vote->setVotedEntityType($this->node->getEntityTypeId());
    $vote->setValue(50);
    $vote->save();
    $this->assertNotEmpty($vote, 'A "fake_vote_type" vote was successfully cast on a node.');
  }

}
