<?php

namespace Drupal\Tests\entity_print\Kernel;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\block\Traits\BlockCreationTrait;

/**
 * Test the print links block.
 *
 * @coversDefaultClass \Drupal\entity_print\Plugin\Block\PrintLinks
 * @group entity_print
 */
class PrintLinkTest extends KernelTestBase {

  use BlockCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['system', 'block', 'entity_print'];

  /**
   * Test placing the block so we pick-up any schema errors.
   */
  public function testPlaceBlock() {
    $this->placeBlock('print_links');
    $this->assertTrue(TRUE);
  }

  /**
   * Test build links.
   *
   * @covers ::build
   */
  public function testBuildLinks() {
    // Ensure only the PDF link is enabled by default.
    $build = $this->getBlock()->build();
    $this->assertSame('pdf', $build['pdf']['#export_type']);
    $this->assertFalse(isset($build['epub']));
    $this->assertFalse(isset($build['word_docx']));

    $build = $this->getBlock(['epub_enabled' => TRUE])->build();
    $this->assertSame('pdf', $build['pdf']['#export_type']);
    $this->assertSame('epub', $build['epub']['#export_type']);
    $this->assertFalse(isset($build['word_docx']));
  }

  /**
   * Gets a block plugin for testing.
   *
   * @param array $config
   *   The block configuration.
   *
   * @return \Drupal\entity_print\Plugin\Block\PrintLinks
   *   The loaded block.
   */
  protected function getBlock(array $config = []) {
    $manager = $this->container->get('plugin.manager.block');
    $entity = $this->createMock('Drupal\Core\Entity\EntityInterface');
    $context = new Context(ContextDefinition::create(), $entity);

    /** @var \Drupal\entity_print\Plugin\Block\PrintLinks $block */
    $block = $manager->createInstance('print_links', $config);
    $block->setContext('entity', $context);

    return $block;
  }

}
