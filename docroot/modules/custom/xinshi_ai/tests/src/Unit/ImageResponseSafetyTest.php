<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\xinshi_ai\Plugin\AiProvider\OpenAiCompatibleImageEditTrait;
use PHPUnit\Framework\TestCase;
use Drupal\xinshi_ai\Service\ProviderImageFiles;
use Drupal\xinshi_ai\Service\ImageHttpClientFactory;
use Drupal\xinshi_ai\Service\ImageFileValidator;
use Drupal\xinshi_ai\Service\PublicUrlPolicy;

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

}

final class ImageResponseProbe {
  use OpenAiCompatibleImageEditTrait;

  protected array $configuration = [];
  public array $payload = [];
  public array $binaries = [];
  protected object $client;

  protected function loadClient(): void {
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


  public function __construct() {
    $this->imageFiles = new ProviderImageFiles(new ImageHttpClientFactory(new PublicUrlPolicy()), new ImageFileValidator());
  }

  public function parse(array $response): array {
    return $this->parseImageResponse($response, 'gpt-image-1');
  }
}
