<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai_usage\Contract\PriceCatalogFetcher;
use Drupal\xinshi_ai_usage\Service\LitellmRateConverter;
use Drupal\xinshi_ai_usage\Service\PriceBookException;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Autocomplete of chat or image models for the price book prefill field.
 *
 * Suggestions come from the cached LiteLLM catalog. A cold cache triggers
 * the normal fetch; a failed fetch returns no suggestions instead of an
 * error, so typing in the field never blocks or breaks the form.
 */
final class PriceCatalogAutocompleteController extends ControllerBase {

  public function __construct(
    private readonly PriceCatalogFetcher $catalog,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_ai_usage.price_catalog'));
  }

  /**
   * GET /admin/config/xinshi/ai/usage/pricing/catalog-autocomplete.
   */
  public function handle(Request $request): JsonResponse {
    $query = trim((string) $request->query->get('q', ''));
    $kind = (string) $request->query->get('book_kind', PriceBookService::KIND_SUPPLIER_CHAT);
    if ($query === '' || mb_strlen($query) > 128 || !in_array($kind, PriceBookService::KINDS, TRUE)) {
      return new JsonResponse([]);
    }
    try {
      $catalog = $this->catalog->fetchCatalog();
    }
    catch (PriceBookException) {
      return new JsonResponse([]);
    }
    $items = [];
    foreach (LitellmRateConverter::searchModels($catalog, $query, 10, $kind) as $modelId) {
      $entry = $catalog[$modelId];
      $items[] = [
        'value' => $modelId,
        'label' => $modelId . ' — ' . self::priceSummary($entry, $kind),
      ];
    }
    return new JsonResponse($items);
  }

  /**
   * Formats the catalog list price for the suggestion label.
   */
  private static function priceSummary(array $entry, string $kind): string {
    if ($kind === PriceBookService::KIND_SUPPLIER_IMAGE && isset($entry['output_cost_per_image'])) {
      return '$' . self::usd((float) $entry['output_cost_per_image']) . ' per image';
    }
    if ($kind === PriceBookService::KIND_SUPPLIER_IMAGE) {
      return '$' . self::usd((float) $entry['input_cost_per_token'] * 1_000_000)
        . ' text in / $' . self::usd((float) $entry['input_cost_per_image_token'] * 1_000_000)
        . ' image in / $' . self::usd((float) $entry['output_cost_per_image_token'] * 1_000_000)
        . ' image out per 1M tokens (standard)';
    }
    return '$' . self::usd((float) $entry['input_cost_per_token'] * 1_000_000)
      . ' in / $' . self::usd((float) $entry['output_cost_per_token'] * 1_000_000)
      . ' out per 1M tokens';
  }

  private static function usd(float $value): string {
    $formatted = number_format($value, 4, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
  }

}
