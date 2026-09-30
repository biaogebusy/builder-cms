<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\media\MediaInterface;
use Drupal\xinshi_ai\Exception\ImageSafetyException;
use Drupal\xinshi_ai\Exception\InputImageAccessException;
use Drupal\xinshi_ai\Service\BoundedImageResponse;
use Drupal\xinshi_ai\Service\ImageFileValidator;
use Drupal\xinshi_ai\Service\ImageHttpClientFactory;
use Drupal\xinshi_ai\Service\MediaUploadService;
use Drupal\xinshi_ai\Service\ProviderErrorMapper;
use Drupal\xinshi_ai\Service\ProviderImageFiles;
use Drupal\xinshi_ai\Service\ProviderRequestTrace;
use Drupal\xinshi_ai\Service\PublicUrlPolicy;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use OpenAI\Exceptions\TransporterException;

/**
 * Network and content boundaries, without contacting any remote addresses.
 */
final class ImageBoundaryTest extends TestCase {

  public const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC';

  public static function unsafeUrls(): array {
    return array_map(fn($url) => [$url], [
      'file:///etc/passwd', 'data://text/plain,test', 'ftp://8.8.8.8/x', 'http://localhost/a',
      'http://127.0.0.1/a', 'http://2130706433/a', 'http://0177.0.0.1/a', 'http://0x7f000001/a',
      'http://10.0.0.1/a', 'http://172.16.0.1/a', 'http://192.168.1.1/a', 'http://169.254.169.254/a',
      'http://100.64.0.1/a', 'http://198.18.0.1/a', 'http://224.0.0.1/a', 'http://0.0.0.0/a',
      'http://[::1]/a', 'http://[::ffff:8.8.8.8]/a', 'http://[fc00::1]/a', 'http://[fe80::1]/a',
      'http://[2002:7f00:1::]/a', 'http://[2001:db8::1]/a', 'http://user:pass@8.8.8.8/a',
      'http://8.8.8.8:8080/a', 'http://8.8.8.8/a#fragment', 'http://8.8.8.8\\@127.0.0.1/a',
    ]);
  }

  #[DataProvider('unsafeUrls')]
  public function testUnsafeDestinationsAreRejectedBeforeTransport(string $url): void {
    $trace = new ProviderRequestTrace();
    $stack = HandlerStack::create(function () { $this->fail('No transport call is permitted.'); });
    $stack->push($trace->middleware());
    $stack->unshift((new ImageHttpClientFactory(new PublicUrlPolicy()))->middleware(1024));
    try {
      $stack(new Request('GET', $url), []);
      $this->fail('URL was accepted.');
    }
    catch (ImageSafetyException) {
      $this->assertSame('not_sent', $trace->dispatchState());
    }
  }

  public function testMixedPublicPrivateDnsIsRejected(): void {
    $policy = new class extends PublicUrlPolicy {
      protected function resolve(string $host): array { return ['8.8.8.8', '10.0.0.1']; }
    };
    $this->expectException(ImageSafetyException::class);
    $policy->destination('https://images.example/picture');
  }

  public function testResolutionIsPinnedAndProxyAndRedirectsAreDisabled(): void {
    $policy = new class extends PublicUrlPolicy {
      public int $calls = 0;
      protected function resolve(string $host): array {
        $this->calls++;
        return $this->calls === 1 ? ['8.8.8.8'] : ['127.0.0.1'];
      }
    };
    $factory = new ImageHttpClientFactory($policy);
    $handler = $factory->middleware(5)(function ($request, $options) {
      $this->assertSame(['images.example:443:8.8.8.8'], $options['curl'][CURLOPT_RESOLVE]);
      $this->assertSame('', $options['proxy']);
      $this->assertFalse($options['allow_redirects']);
      $this->assertFalse($options['decode_content']);
      $this->assertTrue($options['verify']);
      $this->assertInstanceOf(BoundedImageResponse::class, $options['sink']);
      // Prepare the real cURL handle to catch unsupported transport options,
      // without executing it or contacting a remote host.
      $curl = new \GuzzleHttp\Handler\CurlFactory(1);
      $easy = $curl->create($request, $options);
      $this->assertInstanceOf(\CurlHandle::class, $easy->handle);
      $curl->release($easy);
      $options['on_headers'](new Response(200, ['Content-Length' => '5']));
      return Create::promiseFor(new Response(200));
    });
    $handler(new Request('GET', 'https://images.example/a'), ['proxy' => 'http://localhost', 'verify' => FALSE])->wait();
    $this->assertSame(1, $policy->calls);
    $this->expectException(ImageSafetyException::class);
    $handler(new Request('GET', 'https://images.example/a'), []);
  }

  public function testRedirectIsRejectedAtHeaders(): void {
    $factory = new ImageHttpClientFactory(new PublicUrlPolicy());
    $handler = $factory->middleware(1024)(function ($request, $options) {
      $options['on_headers'](new Response(302, ['Location' => 'http://127.0.0.1/secret']));
    });
    $this->expectException(ImageSafetyException::class);
    $handler(new Request('GET', 'https://8.8.8.8/a'), []);
  }

  public function testExcessiveContentLengthIsRejectedBeforeBody(): void {
    $handler = (new ImageHttpClientFactory(new PublicUrlPolicy()))->middleware(5)(function ($request, $options) {
      $options['on_headers'](new Response(200, ['Content-Length' => '6']));
    });
    $this->expectException(ImageSafetyException::class);
    $handler(new Request('GET', 'https://8.8.8.8/a'), []);
  }

  public function testPublicIpv6LiteralNeedsNoDnsOverride(): void {
    $handler = (new ImageHttpClientFactory(new PublicUrlPolicy()))->middleware(5)(function ($request, $options) {
      $this->assertSame([], $options['curl'][CURLOPT_RESOLVE]);
      return Create::promiseFor(new Response(200));
    });
    $handler(new Request('GET', 'https://[2606:4700:4700::1111]/image'), [])->wait();
  }

  public function testDownloadedImageUsesBoundedClientAndActualContentType(): void {
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([
      new Response(200, ['Content-Type' => 'application/octet-stream'], base64_decode(self::PNG)),
    ]))]);
    $factory = $this->createMock(ImageHttpClientFactory::class);
    $factory->expects($this->once())->method('create')->with(['timeout' => 30], ImageFileValidator::MAX_BYTES)->willReturn($client);
    $image = (new ProviderImageFiles($factory, new ImageFileValidator()))->fromData(['url' => 'https://8.8.8.8/image']);
    $this->assertSame('image/png', $image->getMimeType());
    $this->assertSame('image.png', $image->getFilename());
  }

  public function testChunkedBodyCannotExceedLimit(): void {
    $sink = new BoundedImageResponse(5);
    $this->assertSame(3, $sink->write('abc'));
    $this->expectException(ImageSafetyException::class);
    $sink->write('def');
  }

  public function testRealImageMimeAndFilenameAreDerivedFromBytes(): void {
    $image = (new ImageFileValidator())->fromBase64(self::PNG);
    $this->assertSame('image/png', $image->getMimeType());
    $this->assertSame('image.png', $image->getFilename());
  }

  public function testSharedUploaderRetainsValidGifSupport(): void {
    $image = (new ImageFileValidator())->fromBase64('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
    $this->assertSame('image/gif', $image->getMimeType());
    $this->assertSame('image.gif', $image->getFilename());
  }

  public static function invalidImages(): array {
    return [[''], ['not a picture'], ['<svg xmlns="http://www.w3.org/2000/svg"/>'], [substr(base64_decode(self::PNG), 0, 32)]];
  }

  #[DataProvider('invalidImages')]
  public function testInvalidContentCannotWriteFilesOrMedia(string $bytes): void {
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects($this->never())->method('getStorage');
    $repository = $this->createMock(FileRepositoryInterface::class);
    $repository->expects($this->never())->method('writeData');
    $files = $this->createMock(FileSystemInterface::class);
    $files->expects($this->never())->method('prepareDirectory');
    $service = new MediaUploadService($entities, $repository, $files, new ImageFileValidator());
    $this->expectException(ImageSafetyException::class);
    $service->fromImageFile(new ImageFile($bytes, 'image/png', 'attack.php'), 7);
  }

  public function testOversizedImageIsRejected(): void {
    $this->expectException(ImageSafetyException::class);
    (new ImageFileValidator())->fromBinary(str_repeat('x', ImageFileValidator::MAX_BYTES + 1));
  }

  public function testOversizedDimensionsAreRejected(): void {
    // A tiny PNG with an oversized IHDR; rejection must precede full decoding.
    $binary = substr_replace(base64_decode(self::PNG), pack('N', 8193), 16, 4);
    $this->expectException(ImageSafetyException::class);
    $this->expectExceptionMessage('dimension limits');
    (new ImageFileValidator())->fromBinary($binary);
  }

  public function testValidImageCannotPersistAProviderControlledExtension(): void {
    $bytes = base64_decode(self::PNG);
    $file = $this->createMock(FileInterface::class);
    $file->method('id')->willReturn(12);
    $file->method('getFilename')->willReturn('image.png');
    $file->expects($this->once())->method('setOwnerId')->with(7);
    $file->expects($this->once())->method('save');
    $repository = $this->createMock(FileRepositoryInterface::class);
    $repository->expects($this->once())->method('writeData')->with($bytes, $this->callback(
      fn(string $path) => str_starts_with($path, 'public://xinshi_ai/') && str_ends_with($path, '-image.png')
    ), $this->anything())->willReturn($file);
    $media = $this->createMock(MediaInterface::class);
    $media->expects($this->once())->method('save');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('create')->with($this->callback(
      fn(array $values) => $values['uid'] === 7 && $values['field_media_image']['target_id'] === 12
    ))->willReturn($media);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('media')->willReturn($storage);
    $service = new MediaUploadService($entities, $repository, $this->createMock(FileSystemInterface::class), new ImageFileValidator());
    $this->assertSame($media, $service->fromImageFile(new ImageFile($bytes, 'text/plain', 'attack.php'), 7));
  }

  public function testInvalidBase64IsRejected(): void {
    $this->expectException(ImageSafetyException::class);
    (new ImageFileValidator())->fromBase64('!invalid!');
  }

  public function testLimitsAndSafetyErrorsAreNotRetried(): void {
    $client = (new ImageHttpClientFactory(new PublicUrlPolicy()))->create(['timeout' => 999]);
    $this->assertSame(300, $client->getConfig('timeout'));
    $this->assertSame(10, $client->getConfig('connect_timeout'));
    $mapper = new ProviderErrorMapper();
    foreach ([new ImageSafetyException('Blocked.'), new InputImageAccessException()] as $error) {
      $code = $mapper->mapToCode($error);
      $this->assertContains($code, ['validation', 'forbidden']);
      $this->assertFalse($mapper->isRetryable($code));
      $transport = new RequestException('Transfer failed.', new Request('GET', 'https://8.8.8.8/image'), NULL, $error);
      $this->assertSame($code, $mapper->mapToCode(new TransporterException($transport)));
    }
  }

}
