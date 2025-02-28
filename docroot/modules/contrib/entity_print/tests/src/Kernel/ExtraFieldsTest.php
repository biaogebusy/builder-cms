<?php

namespace Drupal\Tests\entity_print\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Controller\NodeViewController;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the hook_extra_fields() implementation.
 *
 * @group entity_print
 */
class ExtraFieldsTest extends KernelTestBase {

  use NodeCreationTrait;
  use UserCreationTrait;
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'datetime',
    'entity_print',
    'filter',
  ];

  /**
   * The node we're working with.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    array_map([$this, 'installEntitySchema'], ['user', 'node']);
    $this->installConfig(['system', 'filter', 'node']);
    $this->installSchema('system', ['sequences']);
    $this->createContentType(['type' => 'page', 'display_submitted' => FALSE]);
    $this->node = $this->createNode(['body' => [['value' => 'body text']]]);

    // Configure the display so that the "View PDF" field is displayed.
    EntityViewDisplay::load('node.page.default')
      ->setComponent('entity_print_view_pdf', ['weight' => -2])
      ->setComponent('body', ['type' => 'text_default', 'weight' => 0])
      ->save();

    // Create a user with permission to view the links.
    $account = $this->createUser([
      'access content',
      'entity print access type node',
      'administer nodes',
    ]);
    $this->container->get('current_user')->setAccount($account);
  }

  /**
   * Test the access control for the extra fields.
   */
  public function testExtraFieldAccess() {
    $controller = NodeViewController::create($this->container);
    $renderer = $this->container->get('renderer');

    // The View PDF links are rendered.
    $build = $controller->view($this->node, 'default');
    $text = (string) $renderer->renderPlain($build);
    $this->assertStringContainsString('View PDF', $text);

    // Change to the anonymous user.
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());

    // The View PDF links are not rendered because we don't have access.
    $build = $controller->view($this->node, 'default');
    $text = (string) $renderer->renderPlain($build);
    $this->assertStringNotContainsString('View PDF', $text);
  }

  /**
   * Ensure the weight is correctly assigned during rendering.
   */
  public function testExtraFieldWeight() {
    $controller = NodeViewController::create($this->container);
    $renderer = $this->container->get('renderer');
    $build = $controller->view($this->node, 'default');
    $text = (string) $renderer->renderPlain($build);

    $this->assertTrue(stripos($text, 'View PDF') < stripos($text, 'body text'), 'View PDF link appears first');

    // Change the weight so the View PDF goes to the end.
    EntityViewDisplay::load('node.page.default')
      ->setComponent('entity_print_view_pdf', ['weight' => 10])
      ->save();

    $build = $controller->view($this->node, 'default');
    $text = (string) $renderer->renderPlain($build);
    $this->assertTrue(stripos($text, 'View PDF') > stripos($text, 'body text'), 'View PDF link appears last');
  }

}
