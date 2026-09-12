<?php

declare(strict_types=1);

namespace Drupal\Tests\diff\Functional;

use Drupal\block_content\BlockContentTypeInterface;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests block content diff integration.
 */
#[Group('diff')]
#[RunTestsInSeparateProcesses]
final class BlockContentDiffTest extends BrowserTestBase {

  use DiffTestTrait;

  protected BlockContentTypeInterface $blockContentType;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'diff',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $blockContentType = BlockContentType::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomMachineName(),
    ]);
    $blockContentType->save();
    $this->blockContentType = $blockContentType;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTestEntity(): ContentEntityInterface & RevisionLogInterface {
    return BlockContent::create([
      'type' => $this->blockContentType->id(),
      'info' => $this->randomMachineName(),
      'reusable' => TRUE,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getTestUser(): AccountInterface {
    return $this->drupalCreateUser(['administer block content']);
  }

}
