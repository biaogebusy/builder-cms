<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel\MessageExample;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Kernel tests for message_example token replacements.
 *
 * @group Message
 */
class MessageExampleTokensTest extends KernelTestBase {

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
    $this->installConfig(['filter', 'message', 'node', 'comment', 'message_example', 'system']);

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

    $this->assertNotFalse(
      $this->container->get('module_handler')
        ->loadInclude('message_example', 'inc', 'message_example.tokens')
    );
    $this->setCurrentUser($this->createUser(['access content']));
  }

  /**
   * Tests message_example_token_info().
   */
  public function testTokenInfo(): void {
    $info = \message_example_token_info();
    $this->assertArrayHasKey('message', $info['types']);
    $this->assertArrayHasKey('user-name', $info['tokens']['message']);
    $this->assertArrayHasKey('node-url', $info['tokens']['message']);
    $this->assertArrayHasKey('comment-url', $info['tokens']['message']);
  }

  /**
   * Tests working token replacements for node-based messages.
   */
  public function testNodeMessageTokens(): void {
    $account = $this->createUser([], 'token_author');
    $node = Node::create([
      'type' => 'article',
      'title' => 'Token node',
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

    $bubbleable = new BubbleableMetadata();
    $replacements = \message_example_tokens('message', [
      'user-name' => '[message:user-name]',
      'user-url' => '[message:user-url]',
      'node-title' => '[message:node-title]',
      'node-render' => '[message:node-render]',
    ], ['message' => $message], [], $bubbleable);

    $this->assertEquals($account->label(), $replacements['[message:user-name]']);
    $this->assertStringContainsString((string) $account->id(), $replacements['[message:user-url]']);
    $this->assertEquals('Token node', $replacements['[message:node-title]']);
    $this->assertNotEmpty($replacements['[message:node-render]']);
  }

  /**
   * Tests comment-url token (currently returns the comment ID).
   */
  public function testCommentUrlTokenReturnsId(): void {
    $account = $this->createUser();
    $node = Node::create([
      'type' => 'article',
      'title' => 'Comment token node',
      'uid' => $account->id(),
      'status' => 1,
    ]);
    $node->save();

    $comment = Comment::create([
      'comment_type' => 'comment',
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'field_name' => 'comment',
      'subject' => 'Token comment',
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

    $bubbleable = new BubbleableMetadata();
    $replacements = \message_example_tokens('message', [
      'comment-url' => '[message:comment-url]',
      'node-title' => '[message:node-title]',
    ], ['message' => $message], [], $bubbleable);

    // Current behavior returns the comment ID, not a URL.
    $this->assertEquals($comment->id(), $replacements['[message:comment-url]']);
    $this->assertEquals('Comment token node', $replacements['[message:node-title]']);
  }

  /**
   * Tests node-url token fatal on modern Drupal (Entity::url() removed).
   */
  public function testNodeUrlTokenFatal(): void {
    $account = $this->createUser();
    $node = Node::create([
      'type' => 'article',
      'title' => 'Broken url token',
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

    $this->expectException(\Error::class);
    $bubbleable = new BubbleableMetadata();
    \message_example_tokens('message', [
      'node-url' => '[message:node-url]',
    ], ['message' => $message], [], $bubbleable);
  }

}
