<?php

namespace Drupal\Tests\ai\Unit\Traits\File;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\ai\Traits\File\FileMimeTypeTrait;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the FileMimeTypeTrait trait works correctly.
 *
 * @group ai
 * @covers \Drupal\ai\Traits\File\FileMimeTypeTrait
 */
class FileMimeTypeTraitTest extends TestCase {

  use FileMimeTypeTrait;

  /**
   * The mime type guesser mock.
   *
   * @var \Drupal\Core\File\MimeType\MimeTypeGuesser|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $mimeTypeGuesser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create a mock for the MimeTypeGuesser.
    $this->mimeTypeGuesser = $this->createMock(MimeTypeGuesser::class);

    // Create a container and set the 'file.mime_type.guesser' service.
    $container = new ContainerBuilder();
    $container->set('file.mime_type.guesser', $this->mimeTypeGuesser);
    \Drupal::setContainer($container);

  }

  /**
   * Tests the getFileMimeTypeGuesser method.
   */
  public function testGetFileMimeTypeGuesser() {
    $this->assertSame($this->mimeTypeGuesser, $this->getFileMimeTypeGuesser());
  }

}
