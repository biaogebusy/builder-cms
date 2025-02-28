<?php

namespace Drupal\Tests\entity_print\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Controller\NodePreviewController;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the entity print link in node preview.
 *
 * @group entity_print
 */
class NodePreviewTest extends KernelTestBase {

  /**
   * Collected errors.
   *
   * @var array
   */
  protected $errors = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'entity_print', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    set_error_handler([$this, 'errorHandler']);
  }

  /**
   * Tests that entity print link is not displayed in node preview.
   */
  public function testNodePreview() {
    $this->installConfig(['system', 'user']);
    $this->config('system.theme.global')
      ->set('features.node_user_picture', FALSE)->save();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->container->get('router.builder')->rebuild();

    NodeType::create(['type' => 'whatever', 'name' => 'What?'])->save();
    // Deliberately we are not saving the node, so the node misses the ID.
    $node = Node::create(['type' => 'whatever', 'title' => 'Buzz']);
    $node->in_preview = TRUE;

    $controller = NodePreviewController::create($this->container);
    $build = $controller->view($node);
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $renderer->renderPlain($build);
    $this->assertNotError("array_flip(): Can only flip STRING and INTEGER values!", E_WARNING);
  }

  /**
   * Asserts that a specific error has been triggered.
   *
   * @param string $errstr
   *   Error message.
   * @param int $errno
   *   Error level.
   */
  protected function assertNotError($errstr, $errno) {
    $status = FALSE;
    foreach ($this->errors as $error) {
      if ($error['errstr'] === $errstr && $error['errno'] === $errno) {
        $status = TRUE;
        break;
      }
    }
    $this->assertFalse($status, "Error: '$errstr' (level $errno) was not triggered.");
  }

  /**
   * Provides an error handler for purposes of this test.
   *
   * See set_error_handler() for parameter definitions.
   *
   * @see set_error_handler()
   */
  public function errorHandler($errno, $errstr, $errfile, $errline) {
    $this->errors[] = compact('errno', 'errstr', 'errfile', 'errline');
  }

}
