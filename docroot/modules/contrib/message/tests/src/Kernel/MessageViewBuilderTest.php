<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Kernel tests for the message view builder.
 *
 * @coversDefaultClass \Drupal\message\MessageViewBuilder
 *
 * @group Message
 */
class MessageViewBuilderTest extends KernelTestBase {

  use MessageTemplateCreateTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'message',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'message', 'system', 'user']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
  }

  /**
   * Tests partial and non-partial display components.
   *
   * @covers ::view
   */
  public function testViewWithPartialAndFieldComponents(): void {
    $template = $this->createMessageTemplate(
      'view_builder_tpl',
      'View builder',
      'Description',
      ['Partial zero text', 'Partial one text']
    );

    $account = $this->createUser([], 'view_builder_author');
    $message = Message::create([
      'template' => $template->id(),
      'uid' => $account->id(),
    ]);
    $message->save();

    $display = EntityViewDisplay::create([
      'targetEntityType' => 'message',
      'bundle' => $template->id(),
      'mode' => 'full',
      'status' => TRUE,
    ]);
    $display->setComponent('partial_0', [
      'type' => 'string',
      'weight' => 0,
      'region' => 'content',
    ]);
    $display->setComponent('uid', [
      'type' => 'entity_reference_label',
      'weight' => 1,
      'region' => 'content',
      'settings' => ['link' => FALSE],
    ]);
    $display->save();

    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('message')
      ->view($message, 'full');

    $this->assertArrayHasKey('partial_0', $build);
    $this->assertStringContainsString('Partial zero text', (string) $build['partial_0']['#markup']);
    // Non-partial components take the getSingleFieldDisplay() branch.
    $this->assertArrayHasKey('uid', $build);
    $this->assertNotEmpty($build['uid']);
  }

}
