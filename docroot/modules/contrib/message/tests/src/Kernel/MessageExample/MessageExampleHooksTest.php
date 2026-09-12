<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel\MessageExample;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;

/**
 * Kernel tests for message_example.module hooks.
 *
 * @group Message
 */
class MessageExampleHooksTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'field',
    'filter',
    'message',
    'message_example',
    'node',
    'system',
    'text',
    'token',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('message');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['filter', 'message', 'node', 'comment', 'message_example']);

    if (!FilterFormat::load('basic_html')) {
      FilterFormat::create([
        'format' => 'basic_html',
        'name' => 'Basic HTML',
        'filters' => [],
      ])->save();
    }

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    CommentType::create([
      'id' => 'comment',
      'label' => 'Comment',
      'target_entity_type_id' => 'node',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'comment',
      'type' => 'comment',
      'entity_type' => 'node',
      'settings' => [
        'comment_type' => 'comment',
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => 'comment',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Comments',
      'settings' => [
        'default_mode' => 1,
        'per_page' => 50,
        'anonymous' => 0,
        'form_location' => 1,
        'preview' => 1,
      ],
    ])->save();

    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('comment')->save();
    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('comment')->save();
    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'teaser',
      'status' => TRUE,
    ])->save();

    $this->assertNotNull(
      $this->container->get('entity_type.manager')
        ->getStorage('message_template')
        ->load('example_create_node')
    );
  }

  /**
   * Tests node insert creates an example message.
   */
  public function testNodeInsertCreatesMessage(): void {
    $account = $this->createUser();
    $node = Node::create([
      'type' => 'article',
      'title' => 'Example node',
      'uid' => $account->id(),
      'status' => 1,
    ]);
    $node->save();

    $mids = \Drupal::entityQuery('message')
      ->accessCheck(FALSE)
      ->condition('template', 'example_create_node')
      ->condition('field_node_reference.target_id', $node->id())
      ->execute();
    $this->assertCount(1, $mids);
    $message = Message::load(reset($mids));
    $this->assertTrue((bool) $message->get('field_published')->value);
    $this->assertEquals($account->id(), $message->getOwnerId());
  }

  /**
   * Tests comment insert creates an example message.
   */
  public function testCommentInsertCreatesMessage(): void {
    $account = $this->createUser();
    $node = Node::create([
      'type' => 'article',
      'title' => 'Commented node',
      'uid' => $account->id(),
      'status' => 1,
    ]);
    $node->save();

    $comment = Comment::create([
      'comment_type' => 'comment',
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'field_name' => 'comment',
      'subject' => 'A comment',
      'uid' => $account->id(),
      'status' => 1,
      'comment_body' => [
        'value' => 'Body',
        'format' => 'plain_text',
      ],
    ]);
    $comment->save();

    $mids = \Drupal::entityQuery('message')
      ->accessCheck(FALSE)
      ->condition('template', 'example_create_comment')
      ->condition('field_comment_reference.target_id', $comment->id())
      ->execute();
    $this->assertCount(1, $mids);
    $message = Message::load(reset($mids));
    $this->assertTrue((bool) $message->get('field_published')->value);
    $this->assertEquals($account->id(), $message->getOwnerId());
  }

  /**
   * Tests user insert creates an example message.
   */
  public function testUserInsertCreatesMessage(): void {
    $before = \Drupal::entityQuery('message')
      ->accessCheck(FALSE)
      ->condition('template', 'example_user_register')
      ->count()
      ->execute();

    $account = User::create([
      'name' => 'example_user_' . $this->randomMachineName(),
      'status' => 1,
    ]);
    $account->save();

    $after = \Drupal::entityQuery('message')
      ->accessCheck(FALSE)
      ->condition('template', 'example_user_register')
      ->condition('uid', $account->id())
      ->count()
      ->execute();
    $this->assertEquals(1, $after - $before);
  }

  /**
   * Tests publish status sync on node update.
   */
  public function testNodeUpdateSyncsPublishedStatus(): void {
    $account = $this->createUser();
    $node = Node::create([
      'type' => 'article',
      'title' => 'Publish sync',
      'uid' => $account->id(),
      'status' => 1,
    ]);
    $node->save();

    $mids = \Drupal::entityQuery('message')
      ->accessCheck(FALSE)
      ->condition('template', 'example_create_node')
      ->condition('field_node_reference.target_id', $node->id())
      ->execute();
    $message = Message::load(reset($mids));
    $this->assertTrue((bool) $message->get('field_published')->value);

    $node->setUnpublished();
    $node->save();
    $message = Message::load($message->id());
    $this->assertFalse((bool) $message->get('field_published')->value);

    // Unchanged status is a no-op.
    $node->save();
    $message = Message::load($message->id());
    $this->assertFalse((bool) $message->get('field_published')->value);
  }

  /**
   * Tests publish status sync on comment update.
   */
  public function testCommentUpdateSyncsPublishedStatus(): void {
    $account = $this->createUser();
    $node = Node::create([
      'type' => 'article',
      'title' => 'Comment publish sync',
      'uid' => $account->id(),
      'status' => 1,
    ]);
    $node->save();

    $comment = Comment::create([
      'comment_type' => 'comment',
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'field_name' => 'comment',
      'subject' => 'Sync comment',
      'uid' => $account->id(),
      'status' => 1,
      'comment_body' => [
        'value' => 'Body',
        'format' => 'plain_text',
      ],
    ]);
    $comment->save();

    $mids = \Drupal::entityQuery('message')
      ->accessCheck(FALSE)
      ->condition('template', 'example_create_comment')
      ->condition('field_comment_reference.target_id', $comment->id())
      ->execute();
    $message = Message::load(reset($mids));
    $this->assertTrue((bool) $message->get('field_published')->value);

    $comment->setUnpublished();
    $comment->save();
    $message = Message::load($message->id());
    $this->assertFalse((bool) $message->get('field_published')->value);
  }

  /**
   * Tests update helpers early-return when original is missing.
   */
  public function testUpdateWithoutOriginalReturnsEarly(): void {
    $account = $this->createUser();
    $node = Node::create([
      'type' => 'article',
      'title' => 'No original',
      'uid' => $account->id(),
      'status' => 1,
    ]);
    // Do not save; call update hooks with no original.
    \message_example_node_update($node);

    $comment = Comment::create([
      'comment_type' => 'comment',
      'entity_type' => 'node',
      'entity_id' => 1,
      'field_name' => 'comment',
      'subject' => 'No original',
      'uid' => $account->id(),
      'status' => 1,
    ]);
    \message_example_comment_update($comment);
    $this->assertTrue(TRUE);
  }

  /**
   * Tests status sync no-ops when no referencing messages exist.
   */
  public function testUpdateStatusWithNoMessages(): void {
    $account = $this->createUser();
    $node = Node::create([
      'type' => 'article',
      'title' => 'Untracked node',
      'uid' => $account->id(),
      'status' => 1,
    ]);
    $node->save();

    // Remove messages created by insert so status sync has nothing to update.
    $mids = \Drupal::entityQuery('message')
      ->accessCheck(FALSE)
      ->condition('field_node_reference.target_id', $node->id())
      ->execute();
    if ($mids) {
      $storage = $this->container->get('entity_type.manager')->getStorage('message');
      $storage->delete($storage->loadMultiple($mids));
    }

    $node->setUnpublished();
    $node->save();
    $this->assertCount(0, \Drupal::entityQuery('message')
      ->accessCheck(FALSE)
      ->condition('field_node_reference.target_id', $node->id())
      ->execute());
  }

}
