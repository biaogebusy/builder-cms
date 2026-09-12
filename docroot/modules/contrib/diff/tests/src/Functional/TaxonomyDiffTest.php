<?php

declare(strict_types=1);

namespace Drupal\Tests\diff\Functional;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests taxonomy diff integration.
 */
#[Group('diff')]
#[RunTestsInSeparateProcesses]
final class TaxonomyDiffTest extends BrowserTestBase {

  use DiffTestTrait;
  use TaxonomyTestTrait;

  protected VocabularyInterface $vocabulary;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'diff',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->vocabulary = $this->createVocabulary();
  }

  /**
   * {@inheritdoc}
   */
  protected function getTestEntity(): ContentEntityInterface & RevisionLogInterface {
    return $this->createTerm($this->vocabulary);
  }

  /**
   * {@inheritdoc}
   */
  protected function getTestUser(): AccountInterface {
    return $this->drupalCreateUser(['administer taxonomy']);
  }

}
