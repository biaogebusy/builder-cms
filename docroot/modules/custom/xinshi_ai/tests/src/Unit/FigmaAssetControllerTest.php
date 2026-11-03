<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\xinshi_ai\Controller\FigmaAssetController;
use Drupal\xinshi_ai\Service\FigmaAssetUpload;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/** Tests FigmaAssetController in isolation — no site database, no Drupal bootstrap. */
final class FigmaAssetControllerTest extends TestCase {

  private FigmaAssetUpload $upload;
  private FigmaAssetController $controller;
  private LoggerInterface $logger;
  private const URL = 'https://builder.example/sites/default/files/xinshi_ai/2026-09/asset.png';

  protected function setUp(): void {
    $this->upload = $this->createMock(FigmaAssetUpload::class);

    // Keep the effective OAuth account at the controller boundary.
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn('42');

    $container = new ContainerBuilder();
    $container->set('current_user', $account);
    \Drupal::setContainer($container);

    $this->logger = $this->createMock(LoggerInterface::class);
    $this->controller = new FigmaAssetController($this->upload, $this->logger);
  }

  private function request(array $body): Request {
    return Request::create('/api/v3/ai/figma/assets', 'PUT',
      [], [], [], [], json_encode($body));
  }

  private function png(): string {
    // Minimal 1-byte fake PNG blob (enough to pass non-empty check).
    return base64_encode("\x89PNG\r\n\x1a\n");
  }

  public function testSuccessfulUploadReturnsPublicUrl(): void {
    $this->upload->expects(self::once())
      ->method('import')
      ->with(
        self::callback(fn(ImageFile $img) =>
          $img->getMimeType() === 'image/png' && $img->getFilename() === 'figma-asset.png'),
        hash('sha256', base64_decode($this->png())),
        self::callback(fn($account) => $account->id() === '42'),
        '11111111-1111-4111-8111-111111111111',
      )
      ->willReturn(self::URL);

    $response = $this->controller->upload($this->request([
      'filename' => 'figma-asset.png',
      'mimeType' => 'image/png',
      'data' => $this->png(),
    ]), hash('sha256', base64_decode($this->png())), '11111111-1111-4111-8111-111111111111');

    self::assertSame(200, $response->getStatusCode());
    $body = json_decode($response->getContent(), TRUE);
    self::assertSame(self::URL, $body['url']);
    // Symfony reorders cache-control directives; assert the set, not the string.
    self::assertTrue($response->headers->hasCacheControlDirective('private'));
    self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
  }

  public function testUnsupportedMimeTypeReturns400(): void {
    $this->upload->expects(self::never())->method('import');

    $response = $this->controller->upload($this->request([
      'filename' => 'file.svg',
      'mimeType' => 'image/svg+xml',
      'data' => base64_encode('<svg/>'),
    ]), hash('sha256', base64_decode($this->png())), '11111111-1111-4111-8111-111111111111');

    self::assertSame(400, $response->getStatusCode());
    self::assertSame('figma_invalid_request', json_decode($response->getContent(), TRUE)['code']);
  }

  public function testMissingFieldsReturn400(): void {
    $this->upload->expects(self::never())->method('import');

    $response = $this->controller->upload(
      Request::create('/api/v3/ai/figma/assets', 'PUT', [], [], [], [], '{}'),
      hash('sha256', 'empty'), '11111111-1111-4111-8111-111111111111',
    );

    self::assertSame(400, $response->getStatusCode());
  }

  public function testInvalidBase64Returns400(): void {
    $this->upload->expects(self::never())->method('import');

    $response = $this->controller->upload($this->request([
      'filename' => 'figma-asset.png',
      'mimeType' => 'image/png',
      'data' => '!!!not-base64!!!',
    ]), hash('sha256', base64_decode($this->png())), '11111111-1111-4111-8111-111111111111');

    self::assertSame(400, $response->getStatusCode());
  }

  public function testFilenameIsSanitised(): void {
    $this->upload->expects(self::once())
      ->method('import')
      ->with(self::callback(fn(ImageFile $img) => $img->getFilename() === '..-..-etc-passwd.png'))
      ->willReturn(self::URL);

    $this->controller->upload($this->request([
      'filename' => '../../etc/passwd.png',
      'mimeType' => 'image/png',
      'data' => $this->png(),
    ]), hash('sha256', base64_decode($this->png())), '11111111-1111-4111-8111-111111111111');
  }

  public function testServiceExceptionReturns503(): void {
    $this->upload->method('import')->willThrowException(new \RuntimeException('disk full'));
    $this->logger->expects(self::once())->method('error')->with(
      'Figma media upload failed: @class: @message',
      ['@class' => \RuntimeException::class, '@message' => 'disk full'],
    );

    $response = $this->controller->upload($this->request([
      'filename' => 'figma-asset.png',
      'mimeType' => 'image/png',
      'data' => $this->png(),
    ]), hash('sha256', base64_decode($this->png())), '11111111-1111-4111-8111-111111111111');

    self::assertSame(503, $response->getStatusCode());
    self::assertSame('figma_asset_unavailable', json_decode($response->getContent(), TRUE)['code']);
  }

}
