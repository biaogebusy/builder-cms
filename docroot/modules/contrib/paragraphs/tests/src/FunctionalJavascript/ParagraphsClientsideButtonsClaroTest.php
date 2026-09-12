<?php

namespace Drupal\Tests\paragraphs\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test paragraphs user interface.
 *
 * @group paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('paragraphs')]
class ParagraphsClientsideButtonsClaroTest extends ParagraphsClientsideButtonsTest {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected function placeDefaultBlocks() {
    // Claro has all blocks by default, prevent them from being placed twice.
  }

}
