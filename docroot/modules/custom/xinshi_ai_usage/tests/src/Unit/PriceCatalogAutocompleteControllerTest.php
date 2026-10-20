<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\xinshi_ai_usage\Controller\PriceCatalogAutocompleteController;
use Drupal\xinshi_ai_usage\Contract\PriceCatalogFetcher;
use Drupal\xinshi_ai_usage\Service\PriceBookException;
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

  private function fetcher(array $catalog): PriceCatalogFetcher {
    return new class($catalog) implements PriceCatalogFetcher {

      public function __construct(private readonly array $catalog) {}

      public function fetchCatalog(): array {
        return $this->catalog;
      }

    };
  }

}
