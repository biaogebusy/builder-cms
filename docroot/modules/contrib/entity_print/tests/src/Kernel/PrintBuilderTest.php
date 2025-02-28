<?php

namespace Drupal\Tests\entity_print\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * @coversDefaultClass \Drupal\entity_print\PrintBuilder
 * @group entity_print
 */
class PrintBuilderTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'filter',
    'entity_print',
    'entity_print_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'filter']);
    $this->container->get('theme_installer')->install(['stark']);
    $node_type = NodeType::create(['name' => 'Page', 'type' => 'page']);
    $node_type->setDisplaySubmitted(FALSE);
    $node_type->save();
  }

  /**
   * Test the correct filename is generated.
   *
   * @covers ::deliverPrintable
   * @dataProvider outputtedFileDataProvider
   */
  public function testOutputtedFilename($print_engine_id, $file_name) {
    $print_engine = $this->container->get('plugin.manager.entity_print.print_engine')->createInstance($print_engine_id);
    $node = $this->createNode(['title' => 'myfile']);

    ob_start();
    $this->container->get('entity_print.print_builder')->deliverPrintable([$node], $print_engine, TRUE);
    $contents = ob_get_contents();
    ob_end_clean();
    $this->assertTrue(strpos($contents, $file_name) !== FALSE, "The $file_name file was found in $contents");
  }

  /**
   * Provides a data provider for testOutputtedFilename().
   */
  public function outputtedFileDataProvider() {
    return [
      'PDF file' => ['testprintengine', 'myfile.pdf'],
      'Word doc file' => ['test_word_print_engine', 'myfile.docx'],
    ];
  }

  /**
   * Test that you must pass at least 1 entity.
   *
   * @covers ::deliverPrintable
   */
  public function testNoEntities() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('You must pass at least 1 entity');

    $print_engine = $this->container->get('plugin.manager.entity_print.print_engine')->createInstance('testprintengine');
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('You must pass at least 1 entity');
    $this->container->get('entity_print.print_builder')->deliverPrintable([], $print_engine, TRUE);
  }

  /**
   * Test that CSS is parsed from our test theme correctly.
   */
  public function testEntityPrintThemeCss() {
    $theme = 'entity_print_test_theme';
    $this->container->get('theme_installer')->install([$theme]);
    $this->config('system.theme')
      ->set('default', $theme)
      ->save();
    $node = $this->createNode();

    // Test the global CSS is there.
    $html = $this->container->get('entity_print.print_builder')->printHtml($node, TRUE, FALSE);
    $this->assertStringContainsString('entity-print.css', $html);

    // Disable the global CSS and test it is not there.
    $html = $this->container->get('entity_print.print_builder')->printHtml($node, FALSE, FALSE);
    $this->assertStringNotContainsString('entity-print.css', $html);

    // Assert that the css files have been parsed out of our test theme.
    $this->assertStringContainsString('entityprint-all.css', $html);
    $this->assertStringContainsString('entityprint-page.css', $html);
    $this->assertStringContainsString('entityprint-node.css', $html);

    // Test that CSS was added from hook_entity_print_css(). See the
    // entity_print_test module for the implementation.
    $this->assertStringContainsString('entityprint-module.css', $html);
  }

  /**
   * Test that a file blob is successfully saved.
   */
  public function testFileSaved() {
    $builder = $this->container->get('entity_print.print_builder');
    $print_engine = $this->container->get('plugin.manager.entity_print.print_engine')->createInstance('testprintengine');
    $node = $this->createNode([]);

    // Print builder generates a filename for us.
    $uri = $builder->savePrintable([$node], $print_engine);
    $this->assertRegExp('#public://(.*)\.pdf#', $uri);

    $filename = $this->randomMachineName() . 'pdf';
    $uri = $builder->savePrintable([$node], $print_engine, 'public', $filename);
    $this->assertEquals("public://$filename", $uri);

    // Test the file contents.
    $this->assertEquals('Using testprintengine', file_get_contents($uri));
  }

}
