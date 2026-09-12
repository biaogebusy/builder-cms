<?php

namespace Drupal\Tests\message\Kernel;

use Drupal\Core\Language\Language;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\message\MessageException;
use Drupal\message\MessageInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Kernel tests for the Message entity.
 *
 * @group Message
 *
 * @coversDefaultClass \Drupal\message\Entity\Message
 */
class MessageTest extends KernelTestBase {

  use MessageTemplateCreateTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'message', 'user', 'system'];

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A message template to test with.
   *
   * @var \Drupal\message\MessageTemplateInterface
   */
  protected $messageTemplate;

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    parent::setUp();

    $this->installConfig(['filter']);
    $this->installEntitySchema('message');
    $this->installEntitySchema('user');

    if (version_compare(\Drupal::VERSION, '10.2.0', '<')) {
      $this->installSchema('system', ['sequences']);
    }

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->messageTemplate = $this->createMessageTemplate(mb_strtolower($this->randomMachineName()), $this->randomString(), $this->randomString(), []);
  }

  /**
   * Tests attempting to create a message without a template.
   */
  public function testMissingTemplate() {
    $this->expectException(MessageException::class);
    $message = Message::create(['template' => 'missing']);
    $message->save();
  }

  /**
   * Tests getting the user.
   */
  public function testGetOwner() {
    $message = Message::create(['template' => $this->messageTemplate->id()]);
    $account = $this->createUser();
    $message->setOwner($account);
    $this->assertEquals($account->id(), $message->getOwnerId());

    $owner = $message->getOwner();
    $this->assertEquals($account->id(), $owner->id());
  }

  /**
   * Tests for getText.
   *
   * @covers ::getText
   */
  public function testGetText() {
    // Test with missing message template.
    /** @var \Drupal\message\Entity\Message $message */
    $message = $this->entityTypeManager->getStorage('message')->create(['template' => 'no_exists']);
    $this->assertEmpty($message->getText());

    // Non-existent delta.
    /** @var \Drupal\message\Entity\Message $message */
    $message = $this->entityTypeManager->getStorage('message')->create(['template' => $this->messageTemplate->id()]);
    $this->assertEmpty($message->getText(Language::LANGCODE_NOT_SPECIFIED, 123));

    // Verify token clearing disabled.
    $this->messageTemplate->setSettings([
      'token options' => [
        'token replace' => TRUE,
        'clear' => FALSE,
      ],
    ]);
    $this->messageTemplate->set('text', [
      [
        'value' => 'foo [fake:token] and [message:author:name]',
        'format' => filter_default_format(),
      ],
    ]);
    $this->messageTemplate->save();
    /** @var \Drupal\message\Entity\Message $message */
    $message = $this->entityTypeManager->getStorage('message')->create([
      'template' => $this->messageTemplate->id(),
    ]);
    $text = $message->getText();
    $this->assertEquals(1, count($text));
    $this->assertEquals('<p>foo [fake:token] and [message:author:name]</p>' . "\n", (string) $text[0]);

    // Verify token clearing enabled.
    $this->messageTemplate->setSettings([
      'token options' => [
        'token replace' => TRUE,
        'clear' => TRUE,
      ],
    ]);
    $this->messageTemplate->save();
    /** @var \Drupal\message\Entity\Message $message */
    $message = $this->entityTypeManager->getStorage('message')->create([
      'template' => $this->messageTemplate->id(),
    ]);
    $text = $message->getText();
    $this->assertEquals(1, count($text));
    $this->assertEquals('<p>foo  and </p>' . "\n", (string) $text[0]);

    // Verify token replacement.
    $account = $this->createUser();
    $message->setOwner($account);
    $message->save();
    $text = $message->getText();
    $this->assertEquals(1, count($text));
    $this->assertEquals('<p>foo  and ' . $account->getAccountName() . "</p>\n", (string) $text[0]);

    // Disable token processing.
    $this->messageTemplate->setSettings([
      'token options' => [
        'token replace' => FALSE,
        'clear' => TRUE,
      ],
    ]);
    $this->messageTemplate->save();
    $text = $message->getText();
    $this->assertEquals(1, count($text));
    $this->assertEquals('<p>foo [fake:token] and [message:author:name]</p>' . "\n", (string) $text[0]);
  }

  /**
   * Tests getting the language.
   *
   * @covers ::getLanguage
   */
  public function testGetLanguage() {
    $message = Message::create(['template' => $this->messageTemplate->id()]);

    // By default no specific language is set.
    $this->assertEquals(Language::LANGCODE_NOT_SPECIFIED, $message->getLanguage());

    // Set a specific language. It should then be returned.
    $message->setLanguage('nl');
    $this->assertEquals('nl', $message->getLanguage());
  }

  /**
   * Tests for getText argument handling.
   *
   * @covers ::getText
   * @covers ::processArguments
   * @covers ::setArguments
   * @covers ::getArguments
   */
  public function testGetTextArgumentProcessing() {
    $this->messageTemplate->setSettings([
      'token options' => [
        'token replace' => FALSE,
        'clear' => TRUE,
      ],
    ]);
    $this->messageTemplate->set('text', [
      [
        'value' => '@foo @replace and @no_replace',
        'format' => filter_default_format(),
      ],
      [
        'value' => 'some @foo other @replace',
        'format' => filter_default_format(),
      ],
    ]);
    $this->messageTemplate->save();

    /** @var \Drupal\message\Entity\Message $message */
    $message = $this->entityTypeManager->getStorage('message')->create([
      'template' => $this->messageTemplate->id(),
    ]);
    // Use the documented map-field shape (flat placeholder keys).
    $message->setArguments([
      '@foo' => 'bar',
      '@replace' => [
        'pass message' => TRUE,
        'arguments' => [
          'bar_replacement',
        ],
        'callback' => [static::class, 'argumentCallback'],
      ],
    ]);
    $message->save();
    $this->assertEquals([
      '@foo' => 'bar',
      '@replace' => [
        'pass message' => TRUE,
        'arguments' => [
          'bar_replacement',
        ],
        'callback' => [static::class, 'argumentCallback'],
      ],
    ], $message->getArguments());

    $text = $message->getText();
    $this->assertEquals(2, count($text));
    $this->assertEquals('<p>bar bar_replacement_' . $message->id() . ' and @no_replace</p>' . "\n", (string) $text[0]);
    $this->assertEquals('<p>some bar other bar_replacement_' . $message->id() . "</p>\n", (string) $text[1]);

    // Do not pass the message.
    /** @var \Drupal\message\Entity\Message $message */
    $message = $this->entityTypeManager->getStorage('message')->create([
      'template' => $this->messageTemplate->id(),
    ]);
    $message->setArguments([
      '@foo' => 'bar',
      '@replace' => [
        'pass message' => FALSE,
        'arguments' => [
          'bar_replacement',
        ],
        'callback' => [static::class, 'argumentCallback'],
      ],
    ]);
    $message->save();
    $text = $message->getText();
    $this->assertEquals(2, count($text));
    $this->assertEquals('<p>bar bar_replacement and @no_replace</p>' . "\n", (string) $text[0]);
    $this->assertEquals('<p>some bar other bar_replacement' . "</p>\n", (string) $text[1]);
  }

  /**
   * Test callback method for ::testGetTextArgumentProcessing().
   *
   * @param string $arg_1
   *   The first argument.
   * @param \Drupal\message\MessageInterface|null $message
   *   The message object.
   *
   * @return string
   *   The text.
   */
  public static function argumentCallback($arg_1, ?MessageInterface $message = NULL) {
    if ($message) {
      // Use the message ID appended to replacement text.
      $text = $arg_1 . '_' . $message->id();
    }
    else {
      $text = $arg_1;
    }
    return $text;
  }

  /**
   * Tests template setter and created-time accessors.
   *
   * @covers ::setTemplate
   * @covers ::getCreatedTime
   * @covers ::setCreatedTime
   * @covers ::getUuid
   */
  public function testTemplateAndTimestamps(): void {
    $message = Message::create(['template' => $this->messageTemplate->id()]);
    $message->setTemplate($this->messageTemplate);
    $this->assertEquals($this->messageTemplate->id(), $message->getTemplate()->id());

    $timestamp = 1700000000;
    $message->setCreatedTime($timestamp);
    $this->assertEquals($timestamp, $message->getCreatedTime());

    $message->save();
    $this->assertNotEmpty($message->getUuid());
  }

  /**
   * Tests querying and labeling messages.
   *
   * @covers ::queryByTemplate
   * @covers ::label
   */
  public function testQueryByTemplateAndLabel(): void {
    $this->assertEquals([], Message::queryByTemplate($this->messageTemplate->id()));

    $message = Message::create(['template' => $this->messageTemplate->id()]);
    $message->save();

    $this->assertEquals([$message->id() => $message->id()], Message::queryByTemplate($this->messageTemplate->id()));
    $label = (string) $message->label();
    $this->assertStringContainsString('Message ID ' . $message->id(), $label);
    $this->assertStringContainsString('template:', $label);
  }

  /**
   * Tests the deprecated deleteMultiple helper.
   *
   * @covers ::deleteMultiple
   *
   * @group legacy
   */
  public function testDeleteMultiple(): void {
    $message_one = Message::create(['template' => $this->messageTemplate->id()]);
    $message_one->save();
    $message_two = Message::create(['template' => $this->messageTemplate->id()]);
    $message_two->save();

    $this->expectDeprecation('\Drupal\message\Entity\Message::deleteMultiple is deprecated in message:1.2.0 and is removed from message:2.0.0. Instead, each entity should call the ::delete() method explicitly. See https://www.drupal.org/project/message/issues/3091343');
    Message::deleteMultiple([$message_one->id(), $message_two->id()]);

    $this->assertNull(Message::load($message_one->id()));
    $this->assertNull(Message::load($message_two->id()));
  }

}
