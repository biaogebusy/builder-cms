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

}

final class ImageResponseProbe {
  use OpenAiCompatibleImageEditTrait;

  protected array $configuration = [];

  public function __construct() {
    $this->imageFiles = new ProviderImageFiles(new ImageHttpClientFactory(new PublicUrlPolicy()), new ImageFileValidator());
  }

  public function parse(array $response): array {
    return $this->parseImageResponse($response, 'gpt-image-1');
  }
}
