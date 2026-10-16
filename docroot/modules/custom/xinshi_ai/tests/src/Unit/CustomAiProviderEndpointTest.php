<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\xinshi_ai\Plugin\AiProvider\CustomAiProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * The wire contract carries the full generation URL; the OpenAI SDK appends the
 * operation path (/images/generations, /images/edits) to the base URI itself, so
 * a submitted suffix must be stripped or the path doubles.
 */
final class CustomAiProviderEndpointTest extends TestCase {

  private function normalize(string $endpoint): string {
    $provider = (new ReflectionClass(CustomAiProvider::class))
      ->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($provider, 'normalizeEndpoint');
    $method->setAccessible(TRUE);
    return $method->invoke($provider, $endpoint);
  }

  public function testGenerationSuffixIsStripped(): void {
    $this->assertSame('https://api.example/v1',
      $this->normalize('https://api.example/v1/images/generations'));
    $this->assertSame('https://api.example/v1',
      $this->normalize('https://api.example/v1/images/generations/'));
    $this->assertSame('https://api.example/V1',
      $this->normalize('https://api.example/V1/Images/Generations'));
  }

  public function testBaseUrlsPassThroughUnchanged(): void {
    $this->assertSame('https://api.example/v1', $this->normalize('https://api.example/v1'));
    $this->assertSame('https://api.example/v1', $this->normalize('https://api.example/v1/'));
    $this->assertSame('https://api.example/openai', $this->normalize('https://api.example/openai'));
  }

}
