<?php

namespace Drupal\Tests\votingapi\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the deletion of votes.
 *
 * @group VotingAPI
 */
class VoteDeletionTest extends BrowserTestBase {

  /**
   * The node object.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'votingapi',
    'votingapi_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->createUser([
      'access administration pages',
      'delete votes',
    ]);

    $this->node = $this->createNode([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]);

    $this->drupalLogin($user);
  }

  /**
   * Tests deleting a vote.
   */
  public function testVoteDeletion(): void {
    $session = $this->assertSession();
    $vote_storage = $this->container->get('entity_type.manager')->getStorage('vote');

    // Save a few votes.
    $values = [
      3 => 'source_1',
      4 => 'source_2',
      5 => 'source_2',
    ];

    foreach ($values as $value => $source) {
      $vote_storage->create([
        'type' => 'vote',
        'entity_id' => $this->node->id(),
        'entity_type' => 'node',
        'user_id' => 0,
        'value' => $value,
        'vote_source' => $source,
      ])->save();
    }

    // Get vote id.
    $vote_id = \Drupal::entityQuery('vote')
      ->condition('vote_source', 'source_1')
      ->accessCheck(TRUE)
      ->execute();

    $vote = $vote_storage->load(reset($vote_id));
    $vote_owner = $vote->getOwner()->getDisplayName();
    $entity_type = $this->node->getEntityType()->getSingularLabel();
    $label = $this->node->label();

    // Delete a vote.
    $this->drupalGet('admin/vote/' . reset($vote_id) . '/delete');
    $session->pageTextContains(
      t('You are about to delete a vote by @user on @entity-type @label. This action cannot be undone.', [
        '@user' => $vote_owner,
        '@entity-type' => $entity_type,
        '@label' => $label,
      ]));
    $this->submitForm([], 'Delete');
    $session->pageTextContains(
      t('The vote by @user on @entity-type @label has been deleted.', [
        '@user' => $vote_owner,
        '@entity-type' => $entity_type,
        '@label' => $label,
      ]));

    // Assert that the vote got deleted and other votes remain.
    $source_1_votes = $vote_storage->getUserVotes(0, 'vote', 'node', 1, 'source_1');
    $this->assertCount(0, $source_1_votes);
    $source_2_votes = $vote_storage->getUserVotes(0, 'vote', 'node', 1, 'source_2');
    $this->assertCount(2, $source_2_votes);
  }

}
