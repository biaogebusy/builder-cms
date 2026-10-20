<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\xinshi_ai\Plugin\AiProvider\OpenAiCompatibleImageEditTrait;
use PHPUnit\Framework\TestCase;
use Drupal\xinshi_ai\Service\ProviderImageFiles;
use Drupal\xinshi_ai\Service\ImageHttpClientFactory;
use Drupal\xinshi_ai\Service\ImageFileValidator;
use Drupal\xinshi_ai\Service\PublicUrlPolicy;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use OpenAI\Exceptions\ErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;

/**
 * Regressions for provider-controlled file protocols and fake image bytes.
 */
final class ImageResponseSafetyTest extends TestCase {

  public function testNonHttpImageUrlIsRejected(): void {
    $this->expectException(\DomainException::class);
    (new ImageResponseProbe())->parse(['data' => [['url' => 'data://text/plain,harmless-test-content']]]);
  }

  public function testNonImageBase64IsRejected(): void {
    $this->expectException(\DomainException::class);
    (new ImageResponseProbe())->parse(['data' => [['b64_json' => base64_encode('not an image')]]]);
  }

  public function testProviderReceivesAllImagesInOrderAndClosesHandles(): void {
    $probe = new ImageResponseProbe();
    $files = [new \Drupal\ai\OperationType\GenericType\ImageFile('first', 'image/png', 'a.png'),
      new \Drupal\ai\OperationType\GenericType\ImageFile('second', 'image/jpeg', 'b.jpg')];
    $input = new \Drupal\xinshi_ai\Service\ReferenceImageInput($files);
    $input->setPrompt('Keep the actor, change the angle');
    try {
      $probe->imageToImage($input, 'fixture');
      $this->fail('The synthetic client must stop before a real response.');
    }
    catch (\RuntimeException $error) {
      $this->assertSame('synthetic stop', $error->getMessage());
    }
    $this->assertSame(['first', 'second'], $probe->binaries);
    $this->assertSame('Keep the actor, change the angle', $probe->payload['prompt']);
    foreach ($probe->payload['image'] as $handle) {
      $this->assertFalse(is_resource($handle));
    }
  }

  #[DataProvider('sdkEditCases')]
  public function testSdkEditPreservesResponseAndRemovesTemporaryFiles(int $count, bool $mask, bool $success): void {
    $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC';
    $before = glob(sys_get_temp_dir() . '/xinshi_ai_edit_*');
    $requests = 0;
    $http = new Client(['handler' => new MockHandler([
      function (RequestInterface $request) use ($count, $mask, $success, $png, &$requests): Response {
        $requests++;
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/images/edits', $request->getUri()->getPath());
        $this->assertStringStartsWith('multipart/form-data;', $request->getHeaderLine('Content-Type'));
        $body = (string) $request->getBody();
        $this->assertSame($count, substr_count($body, 'name="' . ($count === 1 ? 'image' : 'image[]') . '"'));
        $this->assertSame((int) $mask, substr_count($body, 'name="mask"'));
        $this->assertStringContainsString('Keep the actor, change the angle', $body);
        $position = -1;
        for ($i = 0; $i < $count; $i++) {
          $next = strpos($body, 'reference-' . $i);
          $this->assertNotFalse($next);
          $this->assertGreaterThan($position, $next);
          $position = $next;
        }
        if ($mask) {
          $this->assertStringContainsString('mask-bytes', $body);
        }
        $data = $success
          ? ['created' => 1, 'data' => [['b64_json' => $png]]]
          : ['error' => ['message' => 'synthetic provider rejection', 'type' => 'invalid_request_error', 'code' => 'invalid_image']];
        return new Response($success ? 200 : 400, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
      },
    ])]);
    $client = \OpenAI::factory()->withApiKey('fixture-key')->withBaseUri('https://image-provider.invalid/v1')->withHttpClient($http)->make();
    $probe = new ImageResponseProbe($client);
    $images = [];
    for ($i = 0; $i < $count; $i++) {
      $images[] = new \Drupal\ai\OperationType\GenericType\ImageFile('reference-' . $i, 'image/png', 'input.png');
    }
    $input = new \Drupal\xinshi_ai\Service\ReferenceImageInput($images);
    $input->setPrompt('Keep the actor, change the angle');
    if ($mask) {
      $input->setMask(new \Drupal\ai\OperationType\GenericType\ImageFile('mask-bytes', 'image/png', 'mask.png'));
    }
    try {
      try {
        $output = $probe->imageToImage($input, 'fixture');
        $this->assertTrue($success, 'A provider error must remain an error.');
        $this->assertCount(1, $output->getNormalized());
        $this->assertSame(base64_decode($png), $output->getNormalized()[0]->getBinary());
      }
      catch (ErrorException $error) {
        $this->assertFalse($success, 'A successful provider result must survive cleanup.');
        $this->assertSame('synthetic provider rejection', $error->getMessage());
      }
    }
    finally {
      $remaining = array_values(array_diff(glob(sys_get_temp_dir() . '/xinshi_ai_edit_*'), $before));
      // Keep a failed regression from leaving fixtures for the next case.
      foreach ($remaining as $path) {
        unlink($path);
      }
    }
    $this->assertSame(1, $requests);
    $this->assertSame([], $remaining, 'Image and mask files must be removed on success and failure.');
  }

  public static function sdkEditCases(): array {
    return [
      'single success' => [1, FALSE, TRUE],
      'single failure' => [1, FALSE, FALSE],
      'multiple with mask success' => [2, TRUE, TRUE],
      'multiple with mask failure' => [2, TRUE, FALSE],
    ];
  }

}

final class ImageResponseProbe {
  use OpenAiCompatibleImageEditTrait;

  protected array $configuration = [];
  public array $payload = [];
  public array $binaries = [];
  protected object $client;

  protected function loadClient(): void {
    if (isset($this->client)) {
      return;
    }
    $this->client = new class($this) {
      public function __construct(private ImageResponseProbe $probe) {}
      public function images(): self { return $this; }
      public function edit(array $payload): never {
        $this->probe->payload = $payload;
        $this->probe->binaries = array_map(static fn($handle) => stream_get_contents($handle), $payload['image']);
        throw new \RuntimeException('synthetic stop');
      }
    };
  }

  protected function moderationEndpoints(string $prompt): void {}


  public function __construct(?\OpenAI\Client $client = NULL) {
    $this->imageFiles = new ProviderImageFiles(new ImageHttpClientFactory(new PublicUrlPolicy()), new ImageFileValidator());
    if ($client !== NULL) {
      $this->client = $client;
    }
  }

  public function parse(array $response): array {
    return $this->parseImageResponse($response, 'gpt-image-1');
  }
}
