<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Drupal\xinshi_ai\Exception\InputImageAccessException;
use Drupal\xinshi_ai\Service\ImageFileValidator;
use Drupal\xinshi_ai\Service\InputImageAccess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Submission and worker checks against actual files and controlled access rules.
 */
final class InputImageAccessTest extends TestCase {

  private const UUID = '11111111-1111-4111-8111-111111111111';
  private InputImageAccess $service;
  private UserInterface $owner;
  private MediaInterface $media;
  private NodeInterface $job;
  private string $directory;
  private string $uri = 'private://image.png';
  private bool $active = TRUE;
  private bool $permission = TRUE;
  private bool $mediaAllowed = TRUE;
  private bool $fieldAllowed = TRUE;
  private bool $fileAllowed = TRUE;
  private bool $exists = TRUE;
  private string $bundle = 'image';
  private array $headers = ['Content-Type' => 'image/png'];
  private array $switches = [];
  private array $reloads = [];

  protected function setUp(): void {
    $this->directory = sys_get_temp_dir() . '/xinshi-image-access-' . bin2hex(random_bytes(8));
    mkdir($this->directory);
    file_put_contents($this->directory . '/image.png', base64_decode(ImageBoundaryTest::PNG));
    $this->owner = $this->createMock(UserInterface::class);
    $this->owner->method('id')->willReturn(7);
    $this->owner->method('isActive')->willReturnCallback(fn() => $this->active);
    $this->owner->method('hasPermission')->with('create xinshi_ai image job')->willReturnCallback(fn() => $this->permission);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('target_id')->willReturn(11);
    $field->method('access')->willReturnCallback(function ($operation, $account) {
      $this->assertSame($this->owner, $account);
      return $this->fieldAllowed;
    });
    $this->media = $this->createMock(MediaInterface::class);
    $this->media->method('id')->willReturn(9);
    // Shared media: ownership differs, but explicit view/download grants allow it.
    $this->media->method('getOwnerId')->willReturn(99);
    $this->media->method('bundle')->willReturnCallback(fn() => $this->bundle);
    $this->media->method('hasField')->with('field_media_image')->willReturn(TRUE);
    $this->media->method('get')->with('field_media_image')->willReturn($field);
    $this->media->method('access')->willReturnCallback(function ($operation, $account) {
      $this->assertSame($this->owner, $account);
      return $this->mediaAllowed;
    });
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturnCallback(fn() => $this->uri);
    $file->method('access')->willReturnCallback(function ($operation, $account) {
      $this->assertSame('download', $operation);
      $this->assertSame($this->owner, $account);
      return $this->fileAllowed;
    });
    $storages = [];
    foreach (['user' => $this->owner, 'media' => $this->media, 'file' => $file] as $type => $entity) {
      $storage = $this->createMock(EntityStorageInterface::class);
      $storage->method('resetCache')->willReturnCallback(function () use ($type) { $this->reloads[] = $type; });
      $storage->method('load')->willReturnCallback(fn() => $type === 'media' && !$this->exists ? NULL : $entity);
      $storages[$type] = $storage;
    }
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(fn($type) => $storages[$type]);
    $repository = $this->createMock(EntityRepositoryInterface::class);
    $repository->method('loadEntityByUuid')->with('media', self::UUID)->willReturnCallback(fn() => $this->exists ? $this->media : NULL);
    $modules = $this->createMock(ModuleHandlerInterface::class);
    $modules->method('invokeAll')->willReturnCallback(function ($hook) {
      $this->assertSame('file_download', $hook);
      $this->assertSame(7, end($this->switches));
      return $this->headers;
    });
    $switcher = $this->createMock(AccountSwitcherInterface::class);
    $switcher->method('switchTo')->willReturnCallback(function ($account) { $this->switches[] = $account->id(); });
    $switcher->method('switchBack')->willReturnCallback(function () { $this->switches[] = 'restored'; });
    $wrapper = $this->createMock(LocalStream::class);
    $wrapper->method('getDirectoryPath')->willReturn($this->directory);
    $wrapper->method('realpath')->willReturnCallback(fn() => realpath($this->directory . '/image.png'));
    $wrappers = $this->createMock(StreamWrapperManagerInterface::class);
    $wrappers->method('getViaUri')->willReturn($wrapper);
    $this->service = new InputImageAccess($entities, $repository, $modules, $switcher, $wrappers, new ImageFileValidator());
    $reference = $this->createMock(FieldItemListInterface::class);
    $reference->method('__get')->with('target_id')->willReturn(9);
    $reference->method('access')->with('view', $this->owner)->willReturn(TRUE);
    $this->job = $this->createMock(NodeInterface::class);
    $this->job->method('getOwnerId')->willReturn(7);
    $this->job->method('hasField')->with('field_input_image')->willReturn(TRUE);
    $this->job->method('get')->with('field_input_image')->willReturn($reference);
  }

  protected function tearDown(): void {
    @unlink($this->directory . '/image.png');
    rmdir($this->directory);
  }

  public function testAuthorizedSharedImageCanBeSubmittedAndReadByWorker(): void {
    $this->assertSame($this->media, $this->service->forSubmission(self::UUID, $this->owner));
    $this->assertSame('image/png', $this->service->forJob($this->job)->getMimeType());
    $this->assertSame([7, 'restored', 7, 'restored'], $this->switches);
    $this->assertSame(['user', 'file', 'user', 'media', 'file'], $this->reloads);
  }

  public static function deniedCases(): array {
    return [['mediaAllowed', FALSE], ['fieldAllowed', FALSE], ['fileAllowed', FALSE],
      ['headers', []], ['headers', [-1]], ['active', FALSE], ['permission', FALSE],
      ['exists', FALSE], ['bundle', 'document'], ['uri', 'file:///etc/passwd'], ['uri', 'https://example.invalid/image.png']];
  }

  #[DataProvider('deniedCases')]
  public function testDeniedInputCannotBeSubmitted(string $property, mixed $value): void {
    $this->$property = $value;
    try {
      $this->service->forSubmission(self::UUID, $this->owner);
      $this->fail('Input should be denied.');
    }
    catch (InputImageAccessException) {
      $this->assertTrue(!$this->switches || end($this->switches) === 'restored');
    }
  }

  #[DataProvider('deniedCases')]
  public function testAuthorizationRevokedAfterSubmissionIsRechecked(string $property, mixed $value): void {
    $this->service->forSubmission(self::UUID, $this->owner);
    $this->$property = $value;
    $this->expectException(InputImageAccessException::class);
    $this->service->forJob($this->job);
  }

  public function testDeletedFileCannotBeReadByWorker(): void {
    $this->service->forSubmission(self::UUID, $this->owner);
    unlink($this->directory . '/image.png');
    $this->expectException(InputImageAccessException::class);
    $this->service->forJob($this->job);
  }

  public function testPublicImageStillRequiresMediaAccess(): void {
    $this->uri = 'public://image.png';
    $this->headers = [-1];
    $this->assertSame($this->media, $this->service->forSubmission(self::UUID, $this->owner));
    $this->mediaAllowed = FALSE;
    $this->expectException(InputImageAccessException::class);
    $this->service->forJob($this->job);
  }

  public function testMalformedUuidIsRejected(): void {
    $this->expectException(InputImageAccessException::class);
    $this->service->forSubmission(TRUE, $this->owner);
  }

}
