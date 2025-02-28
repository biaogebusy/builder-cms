<?php

namespace Drupal\Tests\message_subscribe\Kernel;

use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\og\Og;
use Drupal\og\OgGroupAudienceHelper;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;

/**
 * Test getting context from entity.
 *
 * @group message_subscribe
 * @requires module og
 */
class ContextTest extends MessageSubscribeTestBase {

  use CommentTestTrait;
  use EntityReferenceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'filter',
    'og',
    'options',
    'taxonomy',
    'text',
  ];

  /**
   * Test comment.
   *
   * @var \Drupal\comment\CommentInterface
   */
  protected $comment;

  /**
   * Test group.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $group;

  /**
   * Group content node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The message subscribers service.
   *
   * @var \Drupal\message_subscribe\SubscribersInterface
   */
  protected $subscribers;

  /**
   * Test terms.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected $terms;

  /**
   * Test users.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $users;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installEntitySchema('comment');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['comment', 'og']);

    foreach (range(1, 3) as $uid) {
      $this->users[$uid] = $this->createUser();
    }

    // Create group node-type.
    $type = $this->createContentType();
    $group_type = $type->id();
    Og::groupTypeManager()->addGroup('node', $group_type);

    // Create node-type.
    $type = $this->createContentType();
    $node_type = $type->id();
    Og::createField(OgGroupAudienceHelper::DEFAULT_FIELD, 'node', $node_type);

    // Enable comments on the node type.
    $this->addDefaultCommentField('node', $node_type);

    // Create vocabulary and terms.
    $vocabulary = Vocabulary::create([
      'vid' => 'terms',
      'name' => 'Terms',
    ]);
    $vocabulary->save();

    // Create terms.
    foreach (range(1, 3) as $i) {
      $this->terms[$i] = Term::create([
        'name' => "term $i",
        'vid' => $vocabulary->id(),
      ]);
      $this->terms[$i]->save();
    }

    // Create a multiple terms-reference field.
    $this->createEntityReferenceField('node', $node_type, 'field_terms_ref', $this->randomString(), 'taxonomy_term', 'default', [], FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    // Create OG group.
    $settings = [];
    $settings['type'] = $group_type;
    $settings['uid'] = $this->users[3]->id();
    $this->group = $this->createNode($settings);

    // Create node.
    $settings = [
      'type' => $node_type,
      'uid' => $this->users[1]->id(),
      'field_terms_ref' => $this->terms,
      OgGroupAudienceHelper::DEFAULT_FIELD => [
        'target_id' => $this->group->id(),
      ],
    ];
    $this->node = $this->createNode($settings);

    // Add comment.
    $settings = [
      'subject' => 'topic',
      'entity_type' => 'node',
      'entity_id' => $this->node->id(),
      'uid' => $this->users[2]->id(),
      'field_name' => 'comment',
      'status' => CommentInterface::PUBLISHED,
    ];
    $this->comment = Comment::create($settings);
    $this->comment->save();

    $this->subscribers = $this->container->get('message_subscribe.subscribers');
  }

  /**
   * Test basic context method.
   */
  public function testGetBasicContext() {
    $node = $this->node;
    $group = $this->group;
    $comment = $this->comment;

    // Get context from comment.
    $context = $this->subscribers->getBasicContext($comment);

    $expected_context = [];
    $expected_context['comment'] = array_combine([$comment->id()], [$comment->id()]);
    $expected_context['node'] = array_combine([
      $node->id(),
      $group->id(),
    ], [
      $node->id(),
      $group->id(),
    ]);

    $expected_context['user'] = array_combine([
      $comment->getOwnerId(),
      $node->getOwnerId(),
      $group->getOwnerId(),
    ], [
      $comment->getOwnerId(),
      $node->getOwnerId(),
      $group->getOwnerId(),
    ]);

    $expected_context['taxonomy_term'] = array_combine(array_keys($this->terms), array_keys($this->terms));

    $this->assertEquals($expected_context['comment'], $context['comment'], 'Correct comment context from comment.');
    $this->assertEquals($expected_context['node'], $context['node'], 'Correct node context from comment.');
    $this->assertEquals($expected_context['taxonomy_term'], $context['taxonomy_term'], 'Correct taxonomy_term context from comment.');
    $this->assertEquals($expected_context['user'], $context['user'], 'Correct user context from comment.');

    // Pass existing context.
    $subscribe_options = ['skip context' => TRUE];
    $original_context = ['node' => [1 => 1], 'user' => [1 => 1]];
    $context = $this->subscribers->getBasicContext($comment, $subscribe_options, $original_context);

    $this->assertEquals($original_context, $context, 'Correct context when skiping context.');
  }

}
