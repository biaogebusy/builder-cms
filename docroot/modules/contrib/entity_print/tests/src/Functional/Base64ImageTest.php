<?php

namespace Drupal\Tests\entity_print\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Render\RenderContext;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\Tests\BrowserTestBase;

/**
 * Test base64 images in exports.
 *
 * @group entity_print
 */
class Base64ImageTest extends BrowserTestBase {

  /**
   * An array of modules to enable.
   *
   * @var array
   */
  protected static $modules = ['user', 'node', 'image', 'entity_print_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The node we're printing.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * The contents of the image for testing.
   *
   * @var string
   */
  protected $imageContents;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->createContentType(['type' => 'page']);

    // Create a image field attached to the node.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'type' => 'image',
      'field_name' => $field_name = mb_strtolower($this->randomMachineName()),
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'page',
      'field_name' => $field_name,
    ])->save();

    $this->imageContents = $this->randomMachineName();
    file_put_contents('public://test.jpg', $this->imageContents);
    $image = File::create([
      'uri' => 'public://test.jpg',
    ]);
    $image->save();
    $this->node = $this->createNode([
      $field_name => [
        'target_id' => $image->id(),
      ],
    ]);

    $component = [
      'type' => 'entity_print_base64_image_formatter',
      'settings' => ['view_mode' => 'default'],
    ];

    EntityViewDisplay::load('node.page.default')
      ->setComponent($field_name, $component)
      ->setStatus(TRUE)
      ->save();
  }

  /**
   * Ensure the base64 formatter renders correctly.
   */
  public function testBase64Formatter() {
    $display = EntityViewDisplay::load('node.page.default');
    $build = $display->build($this->node);
    $renderer = \Drupal::service('renderer');

    $html = (string) $renderer->executeInRenderContext(new RenderContext(), function () use (&$build, $renderer) {
      return $renderer->render($build, TRUE);
    });

    // Ensure the image is rendered as a base64 encoded image.
    $base64_image = base64_encode($this->imageContents);
    $this->assertStringContainsString("data:image/jpeg;charset=utf-8;base64,$base64_image", $html);
  }

}
