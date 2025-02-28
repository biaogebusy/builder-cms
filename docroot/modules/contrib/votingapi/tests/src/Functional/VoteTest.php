<?php

namespace Drupal\Tests\votingapi\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Voting API basics.
 *
 * @group VotingAPI
 */
class VoteTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'votingapi', 'votingapi_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests casting a vote on an entity.
   */
  public function testVotes(): void {
    $vote_query = \Drupal::entityQuery('vote');
    $vote_storage = $this->container->get('entity_type.manager')->getStorage('vote');
    $node = $this->drupalCreateNode(['type' => 'article']);
    $user = $this->drupalCreateUser();

    // There are no votes on this entity yet.
    $query = $vote_query->condition('entity_type', 'node')
      ->condition('entity_id', $node->id())
      ->accessCheck(TRUE);
    $votes = $query->execute();
    $this->assertCount(0, $votes, 'Vote count for a node is initially zero.');

    // Add a vote to a node.
    /** @var \Drupal\votingapi\VoteInterface $vote */
    $vote = $vote_storage->create([
      'type' => 'type',
      'entity_id' => $node->id(),
      'entity_type' => 'node',
      'user_id' => $user->id(),
      'value' => -1,
    ]);
    $vote->save();
    $votes = $query->execute();
    $this->assertCount(1, $votes, 'After a vote is cast on a node, it can be retrieved.');
    $vote = $vote_storage->load(reset($votes));
    $this->assertNotNull($vote, 'Node vote was loaded.');
    $this->assertEquals($user->id(), $vote->getOwnerId(), 'Node vote has correct user.');
    $this->assertEquals(-1, $vote->getValue(), 'Node vote has correct value.');
    $this->assertNotEquals('', $vote->getSource(), 'A vote with no explicit source received the default value.');

    // Add a vote to a user.
    $vote = $vote_storage->create([
      'type' => 'vote',
      'entity_id' => $user->id(),
      'entity_type' => 'user',
    ]);
    $vote->save();

    $vote_query = \Drupal::entityQuery('vote');
    $query = $vote_query->condition('entity_type', 'user')
      ->condition('entity_id', $user->id())
      ->accessCheck(TRUE);
    $votes = $query->execute();
    $this->assertCount(1, $votes, 'After a vote is cast on a user, it can be retrieved.');
    $vote = $vote_storage->load(reset($votes));
    $this->assertNotNull($vote, 'User vote was loaded.');
    $this->assertEquals(0, $vote->getOwnerId(), 'A vote with no explicit user received the default value.');
    $this->assertEquals(0, $vote->getValue(), 'A vote with no explicit value received the default value.');

    // Deleting entity deletes votes.
    $storage_handler = \Drupal::entityTypeManager()->getStorage('user');
    $entities = $storage_handler->loadMultiple([$user->id()]);
    $storage_handler->delete($entities);
    $votes = $query->execute();
    $this->assertCount(0, $votes, 'When an entity is deleted, the votes are also deleted.');
  }

  /**
   * Test vote results.
   */
  public function testVoteResults(): void {
    $vote_storage = $this->container->get('entity_type.manager')->getStorage('vote');
    $node = $this->drupalCreateNode();
    $user = $this->drupalCreateUser();
    $manager = $this->container->get('plugin.manager.votingapi.resultfunction');

    // Save a few votes so that we have data.
    $values = [10, 20, 60];
    foreach ($values as $value) {
      $vote_storage->create([
        'type' => 'vote',
        'entity_id' => $node->id(),
        'entity_type' => 'node',
        'user_id' => $user->id(),
        'value' => $value,
      ])->save();
    }

    $results = $manager->getResults('node', $node->id());

    // Standard results are available and correct.
    $this->assertNotEmpty($results['vote'], 'Results for test vote type are available.');
    $this->assertArrayHasKey('vote_sum', $results['vote'], 'Sum was calculated.');
    $this->assertEquals(90, $results['vote']['vote_sum'], 'Sum is correct.');
    $this->assertArrayHasKey('vote_average', $results['vote'], 'Average was calculated.');
    $this->assertEquals(30, $results['vote']['vote_average'], 'Average is correct.');

    // Check the result of hook_vote_result_alter.
    $this->assertArrayHasKey('ultimate_question', $results['vote'], 'hook_vote_result_alter triggered.');
    $this->assertEquals(42, $results['vote']['ultimate_question'], 'The answer to the ultimate question is 42.');

    // When you remove a result type via the hook, it is no longer available.
    // @todo Replace this with a better assert and fix this so we are actually
    // testing the hook as described. Right now this doesn't do anything.
    $this->assertArrayNotHasKey('test', $results, 'Result removed via alter hook was not calculated.');

    // Contrib modules can add new result types.
    $this->assertArrayHasKey('zebra', $results['vote'], 'New result was calculated.');
    $this->assertEquals(10101, $results['vote']['zebra'], 'New result is correct.');

    // Deleting entity removes results.
    $storage_handler = \Drupal::entityTypeManager()->getStorage('node');
    $entities = $storage_handler->loadMultiple([$node->id()]);
    $storage_handler->delete($entities);
    $results = $manager->getResults('node', $node->id());
    $this->assertEmpty($results, 'When an entity is deleted, the voting results are also deleted.');
  }

  /**
   * Test voting by anonymous users.
   */
  public function testAnonymousVoting(): void {
    $vote_storage = $this->container->get('entity_type.manager')->getStorage('vote');
    $node = $this->drupalCreateNode();

    // Save a few votes from different anonymous users.
    $values = [
      10 => 'source_1',
      20 => 'source_2',
      60 => 'source_2',
    ];
    foreach ($values as $value => $source) {
      $vote_storage->create([
        'type' => 'vote',
        'entity_id' => $node->id(),
        'entity_type' => 'node',
        'user_id' => 0,
        'value' => $value,
        'vote_source' => $source,
      ])->save();
    }

    // Retrieve the votes. For now, just count them.
    $votes_from_source_1 = $vote_storage->getUserVotes(0, 'vote', 'node', 1, 'source_1');
    $votes_from_source_2 = $vote_storage->getUserVotes(0, 'vote', 'node', 1, 'source_2');
    $this->assertCount(1, $votes_from_source_1, 'There is 1 vote from the first source.');
    $this->assertCount(2, $votes_from_source_2, 'There are 2 votes from the second source.');

    // Delete the votes from source_2 and repeat the test.
    $vote_storage->deleteUserVotes(0, 'vote', 'node', 1, 'source_2');
    $votes_from_source_1 = $vote_storage->getUserVotes(0, 'vote', 'node', 1, 'source_1');
    $votes_from_source_2 = $vote_storage->getUserVotes(0, 'vote', 'node', 1, 'source_2');
    $this->assertCount(1, $votes_from_source_1, 'There is still 1 vote from the first source.');
    $this->assertCount(0, $votes_from_source_2, 'There are now 0 votes from the second source.');
  }

}
