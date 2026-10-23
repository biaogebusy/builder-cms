<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\xinshi_ai_usage\Controller\PriceCatalogAutocompleteController;
use Drupal\xinshi_ai_usage\Contract\PriceCatalogFetcher;
use Drupal\xinshi_ai_usage\Service\PriceBookException;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Autocomplete returns priced chat model suggestions and never breaks the form.
 */
final class PriceCatalogAutocompleteControllerTest extends TestCase {

  public function testSuggestsMatchingChatModelsWithListPrices(): void {
    $controller = new PriceCatalogAutocompleteController($this->fetcher([
      'deepseek/deepseek-chat' => [
        'mode' => 'chat',
        'input_cost_per_token' => 0.00000028,
        'output_cost_per_token' => 0.00000042,
      ],
      'text-embedding-3-small' => ['mode' => 'embedding'],
    ]));
    $response = $controller->handle(Request::create('/', 'GET', ['q' => 'deep']));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame([[
      'value' => 'deepseek/deepseek-chat',
      'label' => 'deepseek/deepseek-chat — $0.28 in / $0.42 out per 1M tokens',
    ]], json_decode((string) $response->getContent(), TRUE));

    $this->assertSame([], json_decode((string) $controller->handle(
      Request::create('/', 'GET', ['q' => '']))->getContent(), TRUE));
  }

  public function testAFailedFetchReturnsNoSuggestions(): void {
    $fetcher = new class implements PriceCatalogFetcher {

      public function fetchCatalog(): array {
        throw new PriceBookException('fetch_failed', 'offline');
      }

    };
    $controller = new PriceCatalogAutocompleteController($fetcher);
    $response = $controller->handle(Request::create('/', 'GET', ['q' => 'deep']));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame([], json_decode((string) $response->getContent(), TRUE));
  }

  public function testImageSuggestionsShowTheActualBillingUnits(): void {
    $controller = new PriceCatalogAutocompleteController($this->fetcher([
      'gpt-image-2' => ['mode' => 'image_generation', 'input_cost_per_token' => 0.000005,
        'input_cost_per_image_token' => 0.000008, 'output_cost_per_image_token' => 0.00003],
      'fixed-image' => ['mode' => 'image_edit', 'output_cost_per_image' => 0.04],
      'chat-image' => ['mode' => 'chat', 'input_cost_per_token' => 0.000001, 'output_cost_per_token' => 0.000002],
    ]));
    $response = $controller->handle(Request::create('/', 'GET', [
      'q' => 'image', 'book_kind' => PriceBookService::KIND_SUPPLIER_IMAGE,
    ]));
    $this->assertSame([
      ['value' => 'fixed-image', 'label' => 'fixed-image — $0.04 per image'],
      ['value' => 'gpt-image-2', 'label' => 'gpt-image-2 — $5 text in / $8 image in / $30 image out per 1M tokens (standard)'],
    ], json_decode((string) $response->getContent(), TRUE));
    $this->assertSame([], json_decode((string) $controller->handle(
      Request::create('/', 'GET', ['q' => 'image', 'book_kind' => 'unsupported']))->getContent(), TRUE));
  }

  private function fetcher(array $catalog): PriceCatalogFetcher {
    return new class($catalog) implements PriceCatalogFetcher {

      public function __construct(private readonly array $catalog) {}

      public function fetchCatalog(): array {
        return $this->catalog;
      }

    };
  }

}
