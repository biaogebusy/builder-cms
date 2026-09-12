<?php

declare(strict_types=1);

namespace Drupal\Tests\diff\Functional;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\Entity\Media;
use Drupal\media\MediaTypeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests media diff integration.
 */
#[Group('diff')]
#[RunTestsInSeparateProcesses]
final class MediaDiffTest extends BrowserTestBase {

  use DiffTestTrait;
  use MediaTypeCreationTrait;

  protected MediaTypeInterface $mediaType;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
    'diff',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->mediaType = $this->createMediaType('image', ['id' => 'image']);
    \Drupal::service(EntityDisplayRepositoryInterface::class)->getViewDisplay('media', 'image')->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getTestEntity(): ContentEntityInterface & RevisionLogInterface {
    return Media::create([
      'bundle' => $this->mediaType->id(),
      'name' => $this->randomMachineName(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getTestUser(): AccountInterface {
    return $this->drupalCreateUser(['administer media']);
  }

}
