<?php

namespace Drupal\Tests\votingapi_tokens\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\token\Functional\TokenTestTrait;
use Drupal\votingapi\Entity\Vote;

/**
 * Tests the Voting API basics.
 *
 * @group VotingAPI
 */
class VoteTokenTest extends KernelTestBase {

  use TokenTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'token',
    'system',
    'user',
    'node',
    'field',
    'text',
    'votingapi',
    'votingapi_tokens',
  ];

  /**
   * A test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('router.builder')->rebuild();
    $this->installConfig(['system']);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('vote');
    $this->installEntitySchema('vote_result');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'votingapi', 'field']);

    $node_type = NodeType::create([
      'type' => 'page',
      'name' => 'Basic page',
      'description' => "Use <em>basic pages</em> for your static content, such as an 'About us' page.",
    ]);
    $node_type->save();

    $this->node = Node::create([
      'type' => 'page',
      'title' => 'Source Title',
      'revision_log' => $this->randomMachineName(),
    ]);
    $this->node->save();
  }

  /**
   * Tests token replacements.
   */
  public function testVoteTokens(): void {
    $vote_type = 'vote';

    // First check for non-existing votes.
    $tokens = [
      'vote_count:' . $vote_type => NULL,
      'vote_average:' . $vote_type => NULL,
      'best_vote:' . $vote_type => NULL,
      'worst_vote:' . $vote_type => NULL,
    ];

    $this->assertTokens('votingapi_node_token', ['node' => $this->node], $tokens);

    $vote = Vote::create([
      'type' => $vote_type,
      'entity_id' => $this->node->id(),
      'entity_type' => 'node',
      'user_id' => 0,
      'value' => 1,
    ]);
    $vote->save();

    $tokens = [
      'vote_count:' . $vote_type => '1',
      'vote_average:' . $vote_type => '1',
      'best_vote:' . $vote_type => '1',
      'worst_vote:' . $vote_type => '1',
    ];

    $this->assertTokens('votingapi_node_token', ['node' => $this->node], $tokens);

    $vote = Vote::create([
      'type' => $vote_type,
      'entity_id' => $this->node->id(),
      'entity_type' => 'node',
      'user_id' => 0,
      'value' => 5,
    ]);
    $vote->save();

    $tokens = [
      'vote_count:' . $vote_type => '2',
      'vote_average:' . $vote_type => '3',
      'best_vote:' . $vote_type => '5',
      'worst_vote:' . $vote_type => '1',
    ];

    $this->assertTokens('votingapi_node_token', ['node' => $this->node], $tokens);
  }

}
