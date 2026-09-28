<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\xinshi_ai\Controller\FigmaAssetController;
use Drupal\xinshi_ai\Service\MediaUploadServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/** Tests FigmaAssetController in isolation — no site database, no Drupal bootstrap. */
final class FigmaAssetControllerTest extends TestCase {

  private MediaUploadServiceInterface $upload;
  private FigmaAssetController $controller;
  private const URL = 'https://builder.example/sites/default/files/xinshi_ai/2026-09/asset.png';

  protected function setUp(): void {
    $this->upload = $this->createMock(MediaUploadServiceInterface::class);

    // Minimal container: currentUser() and file_url_generator.
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn('42');

    $urlGenerator = new class {
      public function generateAbsoluteString(string $uri): string {
        return str_replace('public://', 'https://builder.example/sites/default/files/', $uri);
      }
    };

    $container = new ContainerBuilder();
    $container->set('current_user', $account);
    $container->set('file_url_generator', $urlGenerator);
    \Drupal::setContainer($container);

    $this->controller = new FigmaAssetController($this->upload);
  }

  private function mockMedia(string $fileUri): MediaInterface {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn($fileUri);

    $field = new class($file) {
      public function __construct(public readonly object $entity) {}
    };

    $media = $this->createMock(MediaInterface::class);
    $media->method('get')->with('field_media_image')->willReturn($field);
    return $media;
  }

  private function request(array $body): Request {
    return Request::create('/api/v3/ai/figma/assets', 'POST',
      [], [], [], [], json_encode($body));
  }

  private function png(): string {
    // Minimal 1-byte fake PNG blob (enough to pass non-empty check).
    return base64_encode("\x89PNG\r\n\x1a\n");
  }

  public function testSuccessfulUploadReturnsPublicUrl(): void {
    $this->upload->expects(self::once())
      ->method('fromImageFile')
      ->with(
        self::callback(fn(ImageFile $img) =>
          $img->getMimeType() === 'image/png' && $img->getFilename() === 'figma-asset.png'),
        42,
        'Figma asset',
      )
      ->willReturn($this->mockMedia('public://xinshi_ai/2026-09/asset.png'));

    $response = $this->controller->upload($this->request([
      'filename' => 'figma-asset.png',
      'mimeType' => 'image/png',
      'data' => $this->png(),
    ]));

    self::assertSame(200, $response->getStatusCode());
    $body = json_decode($response->getContent(), TRUE);
    self::assertSame(self::URL, $body['url']);
    self::assertSame('private, no-store', $response->headers->get('Cache-Control'));
  }

  public function testUnsupportedMimeTypeReturns400(): void {
    $this->upload->expects(self::never())->method('fromImageFile');

    $response = $this->controller->upload($this->request([
      'filename' => 'file.svg',
      'mimeType' => 'image/svg+xml',
      'data' => base64_encode('<svg/>'),
    ]));

    self::assertSame(400, $response->getStatusCode());
    self::assertSame('figma_invalid_request', json_decode($response->getContent(), TRUE)['code']);
  }

  public function testMissingFieldsReturn400(): void {
    $this->upload->expects(self::never())->method('fromImageFile');

    $response = $this->controller->upload(
      Request::create('/api/v3/ai/figma/assets', 'POST', [], [], [], [], '{}'),
    );

    self::assertSame(400, $response->getStatusCode());
  }

  public function testInvalidBase64Returns400(): void {
    $this->upload->expects(self::never())->method('fromImageFile');

    $response = $this->controller->upload($this->request([
      'filename' => 'figma-asset.png',
      'mimeType' => 'image/png',
      'data' => '!!!not-base64!!!',
    ]));

    self::assertSame(400, $response->getStatusCode());
  }

  public function testFilenameIsSanitised(): void {
    $this->upload->expects(self::once())
      ->method('fromImageFile')
      ->with(self::callback(fn(ImageFile $img) => $img->getFilename() === '..--etc-passwd.png'))
      ->willReturn($this->mockMedia('public://xinshi_ai/2026-09/asset.png'));

    $this->controller->upload($this->request([
      'filename' => '../../etc/passwd.png',
      'mimeType' => 'image/png',
      'data' => $this->png(),
    ]));
  }

  public function testServiceExceptionReturns503(): void {
    $this->upload->method('fromImageFile')->willThrowException(new \RuntimeException('disk full'));

    $response = $this->controller->upload($this->request([
      'filename' => 'figma-asset.png',
      'mimeType' => 'image/png',
      'data' => $this->png(),
    ]));

    self::assertSame(503, $response->getStatusCode());
    self::assertSame('figma_unavailable', json_decode($response->getContent(), TRUE)['code']);
  }

}
