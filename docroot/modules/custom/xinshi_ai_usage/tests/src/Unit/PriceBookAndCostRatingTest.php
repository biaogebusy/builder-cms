<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\xinshi_ai_usage\Service\CostRatingService;
use Drupal\xinshi_ai_usage\Service\PriceBookException;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the purchasing price book and cost rating service against a real database.
 *
 * Uses memory SQLite (same pattern as UsageIngestTest) so the schema is
 * exercised end-to-end instead of mocked.
 */
final class PriceBookAndCostRatingTest extends TestCase {

  private const SITE = 'site-a';
  private const KIND = PriceBookService::KIND_SUPPLIER_CHAT;

  private Connection $database;
  private PriceBookService $priceBook;
  private CostRatingService $costRating;
  private int $now = 1_700_000_000;

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('price_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'price_test');
    $schema = xinshi_ai_usage_schema();
    foreach ($schema as $tableName => $tableSpec) {
      $this->database->schema()->createTable($tableName, $tableSpec);
    }
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentMicroTime')->willReturnCallback(fn() => $this->now + 0.25);
    $this->priceBook = new PriceBookService($this->database, $time);
    $this->costRating = new CostRatingService($this->database, $this->priceBook, $time);
  }

  protected function tearDown(): void {
    Database::removeConnection('price_test');
    parent::tearDown();
  }

  public function testCreateDraftAndActivateReplacesPreviousActive(): void {
    $version = $this->seedRate('v1', 'CNY', $this->sampleRates(2_000_000, 200_000, 1_000_000, 8_000_000));
    $this->assertNull($this->priceBook->loadActive(self::SITE, self::KIND));

    $this->priceBook->activate($version, self::SITE, self::KIND);
    $active = $this->priceBook->loadActive(self::SITE, self::KIND);
    $this->assertSame('v1', $active['version']);
    $this->assertSame('CNY', $active['currency']);

    // Advance time by 2 seconds so v1's effective_from is clearly in the past.
    $this->now += 2;
    $v2 = $this->seedRate('v2', 'CNY', $this->sampleRates(3_000_000, 300_000, 1_500_000, 10_000_000));
    $this->priceBook->activate($v2, self::SITE, self::KIND);
    $active = $this->priceBook->loadActive(self::SITE, self::KIND);
    $this->assertSame('v2', $active['version']);

    // v1 was activated at $now-2s and closed when v2 was activated.
    // Loading at $now-2s (its effective_from) should still return v1.
    $atV1Start = $this->nowMs() - 2000;
    $this->assertSame('v1',
      $this->priceBook->loadActive(self::SITE, self::KIND, $atV1Start)['version']);
  }

  public function testActivatingAnOldDraftDoesNotRewriteEarlierHistory(): void {
    $first = $this->seedRate('history-v1', 'CNY', $this->sampleRates(1, 0, 0, 1));
    $this->priceBook->activate($first, self::SITE, self::KIND);

    $this->now += 1;
    $draftCreatedAt = $this->nowMs();
    $second = $this->seedRate('history-v2', 'CNY', $this->sampleRates(2, 0, 0, 2));
    $this->now += 1;
    $this->priceBook->activate($second, self::SITE, self::KIND);

    $beforeActivation = $this->priceBook->loadActive(
      self::SITE,
      self::KIND,
      $draftCreatedAt + 500,
    );
    $this->assertSame('history-v1', $beforeActivation['version']);
    $this->assertSame('history-v2', $this->priceBook->loadActive(
      self::SITE,
      self::KIND,
      $this->nowMs(),
    )['version']);
  }

  public function testDuplicateVersionLabelIsRejected(): void {
    $this->seedRate('v1', 'CNY', $this->sampleRates(1, 0, 0, 1));
    $this->expectException(PriceBookException::class);
    $this->expectExceptionMessageMatches('/version_exists/');
    $this->seedRate('v1', 'USD', $this->sampleRates(1, 0, 0, 1));
  }

  public function testDraftStoresAuditFields(): void {
    $json = json_encode($this->sampleRates(1, 0, 0, 1));
    $id = $this->priceBook->createDraft(self::SITE, self::KIND, 'audited', 'cny', $json,
      $this->nowMs(), ' quote-2026-09 ', '42');
    $row = $this->database->select(PriceBookService::TABLE, 'p')
      ->fields('p', ['currency', 'source_ref', 'created_by', 'created_at', 'status'])
      ->condition('id', $id)->execute()->fetchAssoc();
    $this->assertSame('CNY', $row['currency']);
    $this->assertSame('quote-2026-09', $row['source_ref']);
    $this->assertSame('42', $row['created_by']);
    $this->assertSame($this->nowMs(), (int) $row['created_at']);
    $this->assertSame(PriceBookService::STATUS_DRAFT, $row['status']);
  }

  public function testRatesValidationRejectsMalformedEntries(): void {
    $cases = [
      'missing accounts' => ['{}', 'invalid_rates'],
      'accounts not an object' => ['{"accounts": "nope"}', 'invalid_rates'],
      'model not an object' => ['{"accounts":{"xinshi":{"models":{"m1":"nope"}}}', 'invalid_rates'],
      'negative rate string' => ['{"accounts":{"xinshi":{"models":{"m1":{"per_million_input":"-1",
        "per_million_cache_read":0,"per_million_cache_write":0,"per_million_output":0}}}}}', 'invalid_rate'],
      'missing rate is invalid' => ['{"accounts":{"xinshi":{"models":{"m1":{}}}}', 'invalid_rates'],
      'all integer rates work' => ['{"accounts":{"xinshi":{"models":{"m1":{
        "per_million_input":42,"per_million_cache_read":0,"per_million_cache_write":0,
        "per_million_output":1}}}}}', NULL],
    ];
    foreach ($cases as $label => [$json, $expectedCode]) {
      if ($expectedCode !== NULL) {
        try {
          $this->priceBook->parseRates($json);
          $this->fail("expected rejection for {$label}");
        }
        catch (PriceBookException $e) {
          $this->assertSame($expectedCode, $e->priceCode, $label);
        }
      }
      else {
        $parsed = $this->priceBook->parseRates($json);
        $this->assertIsArray($parsed['accounts'], $label);
      }
    }
  }

  public function testRatedCostMatchesArithmeticExample(): void {
    // Architecture doc 5.3 example: input 10k (8k cached), output 2k.
    // Cost = (2k * 2 + 8k * 0.2 + 2k * 8) / 1M = 0.0216 CNY = 21600 micros.
    $version = $this->seedRate('doc-example', 'CNY',
      $this->sampleRates(perMillionInput: 2_000_000, perMillionCacheRead: 200_000,
        perMillionCacheWrite: 0, perMillionOutput: 8_000_000));
    $this->priceBook->activate($version, self::SITE, self::KIND);

    $usage = [
      'quality' => 'reported',
      'normalizer_version' => 'chat-completions-inclusive-v1',
      'input_tokens_total' => '10000',
      'input_tokens_cache_read' => '8000',
      'input_tokens_cache_write' => null,
      'output_tokens_total' => '2000',
      'output_tokens_reasoning' => '500',
      'provider_total_tokens' => '12000',
    ];
    $provider = ['account_ref' => 'xinshi', 'requested_model' => 'doc-model'];
    $result = $this->costRating->computeRate(self::SITE, $provider, $usage, $this->nowMs());

    $this->assertSame(CostRatingService::STATE_RATED_ESTIMATE, $result['valuation_state']);
    $this->assertSame('CNY', $result['currency']);
    // Uncached input = 10000 - 8000 = 2000 * 2_000_000 / 1M = 4_000 micros.
    $this->assertSame(4_000, $result['input_cost_micros']);
    // Cache read = 8000 * 200_000 / 1M = 1_600 micros.
    $this->assertSame(1_600, $result['cache_read_cost_micros']);
    // Output = 2000 * 8_000_000 / 1M = 16_000 micros.
    $this->assertSame(16_000, $result['output_cost_micros']);
    $this->assertSame(21_600, $result['total_cost_micros']);
    // Reasoning is a subset of output; it does not add extra cost.
    $this->assertLessThan($result['output_cost_micros'] + 1,
      $result['output_cost_micros']);
  }

  public function testCostRatingUsesIntegerRoundingHalfUp(): void {
    $version = $this->seedRate('r1', 'CNY',
      $this->sampleRates(perMillionInput: 1, perMillionCacheRead: 0,
        perMillionCacheWrite: 0, perMillionOutput: 0));
    $this->priceBook->activate($version, self::SITE, self::KIND);

    // 400_000 tokens * 1 / 1M = 0.4 -> 0 (less than half).
    // 500_000 tokens * 1 / 1M = 0.5 -> 1 (half up).
    $provider = ['account_ref' => 'xinshi', 'requested_model' => 'doc-model'];
    $usageLow = ['quality' => 'reported', 'normalizer_version' => 'v1',
      'input_tokens_total' => '400000', 'input_tokens_cache_read' => null,
      'input_tokens_cache_write' => null, 'output_tokens_total' => '0',
      'output_tokens_reasoning' => null, 'provider_total_tokens' => '400000'];
    $usageHigh = array_replace($usageLow,
      ['input_tokens_total' => '500000', 'provider_total_tokens' => '500000']);

    $low = $this->costRating->computeRate(self::SITE, $provider, $usageLow, $this->nowMs());
    $high = $this->costRating->computeRate(self::SITE, $provider, $usageHigh, $this->nowMs());
    $this->assertSame(0, $low['input_cost_micros']);
    $this->assertSame(1, $high['input_cost_micros']);
  }

  public function testUnknownModelAndMissingUsageAreUnpriced(): void {
    $version = $this->seedRate('v1', 'CNY',
      $this->sampleRates(1, 0, 0, 1));
    $this->priceBook->activate($version, self::SITE, self::KIND);
    $provider = ['account_ref' => 'xinshi', 'requested_model' => 'nope'];
    $usage = ['quality' => 'reported', 'normalizer_version' => 'v1',
      'input_tokens_total' => '10', 'input_tokens_cache_read' => null,
      'input_tokens_cache_write' => null, 'output_tokens_total' => '10',
      'output_tokens_reasoning' => null, 'provider_total_tokens' => '20'];

    $unknown = $this->costRating->computeRate(self::SITE, $provider, $usage, $this->nowMs());
    $this->assertSame(CostRatingService::STATE_UNPRICED, $unknown['valuation_state']);
    $this->assertSame('unknown_model', $unknown['unpriced_reason']);
    $this->assertNull($unknown['total_cost_micros']);

    $realModel = ['account_ref' => 'xinshi', 'requested_model' => 'doc-model'];
    $missing = $this->costRating->computeRate(self::SITE, $realModel, NULL, $this->nowMs());
    $this->assertSame(CostRatingService::STATE_UNPRICED, $missing['valuation_state']);
    $this->assertSame('missing_usage', $missing['unpriced_reason']);

    $invalid = ['quality' => 'invalid', 'normalizer_version' => 'v1',
      'input_tokens_total' => null];
    $result = $this->costRating->computeRate(self::SITE, $realModel, $invalid, $this->nowMs());
    $this->assertSame(CostRatingService::STATE_UNPRICED, $result['valuation_state']);
    $this->assertSame('invalid_usage', $result['unpriced_reason']);
    $this->assertNull($result['total_cost_micros']);
  }

  public function testInvalidReportedContainmentIsUnpriced(): void {
    $version = $this->seedRate('v1', 'CNY', $this->sampleRates(1, 1, 0, 1));
    $this->priceBook->activate($version, self::SITE, self::KIND);
    $provider = ['account_ref' => 'xinshi', 'requested_model' => 'doc-model'];
    $usage = ['quality' => 'reported', 'normalizer_version' => 'v1',
      'input_tokens_total' => '10', 'input_tokens_cache_read' => '11',
      'input_tokens_cache_write' => NULL, 'output_tokens_total' => '1',
      'output_tokens_reasoning' => '2', 'provider_total_tokens' => '11'];

    $result = $this->costRating->computeRate(self::SITE, $provider, $usage, $this->nowMs());
    $this->assertSame(CostRatingService::STATE_UNPRICED, $result['valuation_state']);
    $this->assertSame('invalid_usage', $result['unpriced_reason']);
    $this->assertNull($result['total_cost_micros']);
  }

  public function testResolvedModelControlsPriceSelection(): void {
    $rates = ['accounts' => ['xinshi' => ['models' => [
      'requested-model' => ['per_million_input' => 1, 'per_million_cache_read' => 0,
        'per_million_cache_write' => 0, 'per_million_output' => 0],
      'resolved-model' => ['per_million_input' => 2, 'per_million_cache_read' => 0,
        'per_million_cache_write' => 0, 'per_million_output' => 0],
    ]]]];
    $version = $this->seedRate('resolved', 'CNY', $rates);
    $this->priceBook->activate($version, self::SITE, self::KIND);
    $provider = ['account_ref' => 'xinshi', 'requested_model' => 'requested-model',
      'resolved_model' => 'resolved-model'];
    $usage = ['quality' => 'reported', 'normalizer_version' => 'v1',
      'input_tokens_total' => '1000000', 'input_tokens_cache_read' => NULL,
      'input_tokens_cache_write' => NULL, 'output_tokens_total' => '0',
      'output_tokens_reasoning' => NULL, 'provider_total_tokens' => '1000000'];

    $result = $this->costRating->computeRate(self::SITE, $provider, $usage, $this->nowMs());
    $this->assertSame(2, $result['total_cost_micros']);
  }

  public function testTotalRoundsAfterAccumulatingAllComponents(): void {
    $version = $this->seedRate('aggregate-rounding', 'CNY',
      $this->sampleRates(400_000, 400_000, 0, 0));
    $this->priceBook->activate($version, self::SITE, self::KIND);
    $provider = ['account_ref' => 'xinshi', 'requested_model' => 'doc-model'];
    $usage = ['quality' => 'reported', 'normalizer_version' => 'v1',
      'input_tokens_total' => '2', 'input_tokens_cache_read' => '1',
      'input_tokens_cache_write' => NULL, 'output_tokens_total' => '0',
      'output_tokens_reasoning' => NULL, 'provider_total_tokens' => '2'];

    $result = $this->costRating->computeRate(self::SITE, $provider, $usage, $this->nowMs());
    $this->assertSame(1, $result['total_cost_micros']);
  }

  public function testRateAttemptWritesAndUpsertsCostEntry(): void {
    $version = $this->seedRate('v1', 'CNY',
      $this->sampleRates(2_000_000, 200_000, 0, 8_000_000));
    $this->priceBook->activate($version, self::SITE, self::KIND);
    $provider = ['account_ref' => 'xinshi', 'requested_model' => 'doc-model'];
    $usage = ['quality' => 'reported', 'normalizer_version' => 'v1',
      'input_tokens_total' => '10000', 'input_tokens_cache_read' => '8000',
      'input_tokens_cache_write' => null, 'output_tokens_total' => '2000',
      'output_tokens_reasoning' => null, 'provider_total_tokens' => '12000'];

    $first = $this->costRating->rateAttempt(self::SITE, 'att-1', 1, $provider, $usage, $this->nowMs());
    $this->assertSame(CostRatingService::STATE_RATED_ESTIMATE, $first['valuation_state']);
    $rowCount = (int) $this->database->select(CostRatingService::TABLE, 'c')
      ->countQuery()->execute()->fetchField();
    $this->assertSame(1, $rowCount);

    // Same key and same result -> idempotent no-op, not a second row.
    $second = $this->costRating->rateAttempt(self::SITE, 'att-1', 1, $provider, $usage, $this->nowMs());
    $this->assertSame($first['total_cost_micros'], $second['total_cost_micros']);
    $rowCount = (int) $this->database->select(CostRatingService::TABLE, 'c')
      ->countQuery()->execute()->fetchField();
    $this->assertSame(1, $rowCount);

    // Different revision -> new row.
    $this->costRating->rateAttempt(self::SITE, 'att-1', 2, $provider, $usage, $this->nowMs());
    $rowCount = (int) $this->database->select(CostRatingService::TABLE, 'c')
      ->countQuery()->execute()->fetchField();
    $this->assertSame(2, $rowCount);
  }

  public function testNoActivePriceBookMakesEverythingUnpriced(): void {
    $provider = ['account_ref' => 'xinshi', 'requested_model' => 'doc-model'];
    $usage = ['quality' => 'reported', 'normalizer_version' => 'v1',
      'input_tokens_total' => '10000', 'input_tokens_cache_read' => null,
      'input_tokens_cache_write' => null, 'output_tokens_total' => '2000',
      'output_tokens_reasoning' => null, 'provider_total_tokens' => '12000'];

    $result = $this->costRating->rateAttempt(self::SITE, 'att-x', 1, $provider, $usage, $this->nowMs());
    $this->assertSame(CostRatingService::STATE_UNPRICED, $result['valuation_state']);
    $this->assertSame('no_price_book', $result['unpriced_reason']);
    $this->assertNull($result['total_cost_micros']);
  }

  public function testCustomerKeyCallsAreNeverPlatformCost(): void {
    $version = $this->seedRate('v1', 'CNY',
      $this->sampleRates(1, 0, 0, 1));
    $this->priceBook->activate($version, self::SITE, self::KIND);
    // Even a book entry under that account ref does not make it a purchase.
    $provider = ['account_ref' => 'customer_key', 'requested_model' => 'doc-model'];
    $usage = ['quality' => 'reported', 'normalizer_version' => 'v1',
      'input_tokens_total' => '100', 'input_tokens_cache_read' => null,
      'input_tokens_cache_write' => null, 'output_tokens_total' => '50',
      'output_tokens_reasoning' => null, 'provider_total_tokens' => '150'];

    $result = $this->costRating->computeRate(self::SITE, $provider, $usage, $this->nowMs());
    $this->assertSame(CostRatingService::STATE_UNPRICED, $result['valuation_state']);
    $this->assertSame('customer_key', $result['unpriced_reason']);
    $this->assertNull($result['price_version_id']);
    $this->assertNull($result['total_cost_micros']);
    // Without any book the reason is still the payer, not the missing book.
    $this->assertSame('customer_key', $this->costRating->computeRate('other-site', $provider, $usage,
      $this->nowMs())['unpriced_reason']);
  }

  public function testImageRatesRequirePerImageAndKeepTokenRatesOptional(): void {
    $image = PriceBookService::KIND_SUPPLIER_IMAGE;
    $parsed = $this->priceBook->parseRates(json_encode(['accounts' => ['xinshi' => ['models' => [
      'qwen-image' => ['per_image' => 200_000],
      'gpt-image' => ['per_image' => 0, 'per_million_input' => '5000000', 'per_million_output' => 40_000_000],
    ]]]]), $image);
    $this->assertSame(['per_image' => 200_000, 'per_million_input' => NULL, 'per_million_output' => NULL],
      $parsed['accounts']['xinshi']['models']['qwen-image']);
    $this->assertSame(['per_image' => 0, 'per_million_input' => 5_000_000, 'per_million_output' => 40_000_000],
      $parsed['accounts']['xinshi']['models']['gpt-image']);

    $cases = [
      'missing per_image' => ['{"accounts":{"xinshi":{"models":{"m":{"per_million_input":1}}}}}', 'invalid_rates'],
      'negative per_image' => ['{"accounts":{"xinshi":{"models":{"m":{"per_image":-1}}}}}', 'invalid_rate'],
      'bad token rate' => ['{"accounts":{"xinshi":{"models":{"m":{"per_image":1,"per_million_input":"x"}}}}}', 'invalid_rate'],
    ];
    foreach ($cases as $label => [$json, $code]) {
      try {
        $this->priceBook->parseRates($json, $image);
        $this->fail("expected rejection for {$label}");
      }
      catch (PriceBookException $e) {
        $this->assertSame($code, $e->priceCode, $label);
      }
    }
    // A chat book is not an image book: the chat shape fails image parsing and vice versa.
    try {
      $this->priceBook->parseRates(json_encode($this->sampleRates(1, 0, 0, 1)), $image);
      $this->fail('chat rates must not parse as image rates');
    }
    catch (PriceBookException $e) {
      $this->assertSame('invalid_rates', $e->priceCode);
    }
    try {
      $this->priceBook->parseRates('{"accounts":{"xinshi":{"models":{"m":{"per_image":1}}}}}');
      $this->fail('image rates must not parse as chat rates');
    }
    catch (PriceBookException $e) {
      $this->assertSame('invalid_rates', $e->priceCode);
    }
    try {
      $this->priceBook->parseRates('{}', 'supplier_video');
      $this->fail('unknown kinds are rejected');
    }
    catch (PriceBookException $e) {
      $this->assertSame('invalid_kind', $e->priceCode);
    }
  }

  public function testImageAttemptsAreRatedPerImageFromTheImageBook(): void {
    // A chat book alone does not price images.
    $chat = $this->seedRate('chat-v1', 'CNY', $this->sampleRates(1, 0, 0, 1));
    $this->priceBook->activate($chat, self::SITE, self::KIND);
    $provider = ['account_ref' => 'xinshi', 'requested_model' => 'qwen-image'];
    $fourImages = $this->imageUsage(4);
    $result = $this->costRating->computeRate(self::SITE, $provider, $fourImages, $this->nowMs());
    $this->assertSame('no_price_book', $result['unpriced_reason']);

    $this->now += 1;
    $book = $this->priceBook->createDraft(self::SITE, PriceBookService::KIND_SUPPLIER_IMAGE, 'img-v1', 'CNY',
      json_encode(['accounts' => ['xinshi' => ['models' => [
        'qwen-image' => ['per_image' => 200_000],
        'gpt-image' => ['per_image' => 0, 'per_million_input' => 5_000_000, 'per_million_output' => 40_000_000],
        'hybrid-image' => ['per_image' => 100_000, 'per_million_input' => 1_000_000],
      ]]]]), $this->nowMs());
    $this->priceBook->activate($book, self::SITE, PriceBookService::KIND_SUPPLIER_IMAGE);
    $this->now += 1;

    // 4 images * 0.2 CNY = 800_000 micros; no tokens reported, none charged.
    $result = $this->costRating->computeRate(self::SITE, $provider, $fourImages, $this->nowMs());
    $this->assertSame(CostRatingService::STATE_RATED_ESTIMATE, $result['valuation_state']);
    $this->assertSame('CNY', $result['currency']);
    $this->assertSame(800_000, $result['image_cost_micros']);
    $this->assertSame(0, $result['input_cost_micros']);
    $this->assertSame(0, $result['output_cost_micros']);
    $this->assertNull($result['cache_read_cost_micros']);
    $this->assertSame(800_000, $result['total_cost_micros']);

    // Zero images (the supplier returned an empty list) is a rated zero, not unpriced.
    $this->assertSame(0, $this->costRating->computeRate(self::SITE, $provider, $this->imageUsage(0),
      $this->nowMs())['total_cost_micros']);

    // Token-billed image model: 1 image at 0 + 1000 input * 5 + 5000 output * 40 per million.
    $gpt = ['account_ref' => 'xinshi', 'requested_model' => 'gpt-image'];
    $result = $this->costRating->computeRate(self::SITE, $gpt, $this->imageUsage(1, 1000, 5000), $this->nowMs());
    $this->assertSame(0, $result['image_cost_micros']);
    $this->assertSame(5_000, $result['input_cost_micros']);
    $this->assertSame(200_000, $result['output_cost_micros']);
    $this->assertSame(205_000, $result['total_cost_micros']);

    // The image count is the billing unit even when it exceeds the request.
    // 3 images at 0.1 + 500 input tokens at 1 per million = 300_000 + 500.
    $hybrid = ['account_ref' => 'xinshi', 'requested_model' => 'hybrid-image'];
    $result = $this->costRating->computeRate(self::SITE, $hybrid, $this->imageUsage(3, 500), $this->nowMs());
    $this->assertSame(300_500, $result['total_cost_micros']);

    // Tokens reported for a model whose book entry has no token rate: unpriced, never "just the images".
    $result = $this->costRating->computeRate(self::SITE, $provider, $this->imageUsage(2, 100, 200), $this->nowMs());
    $this->assertSame(CostRatingService::STATE_UNPRICED, $result['valuation_state']);
    $this->assertSame('token_rate_missing', $result['unpriced_reason']);
    $result = $this->costRating->computeRate(self::SITE, $hybrid, $this->imageUsage(1, 100, 200), $this->nowMs());
    $this->assertSame('token_rate_missing', $result['unpriced_reason']);

    // Unknown image model, and an image book does not price chat attempts.
    $result = $this->costRating->computeRate(self::SITE, ['account_ref' => 'xinshi', 'requested_model' => 'nope'],
      $fourImages, $this->nowMs());
    $this->assertSame('unknown_model', $result['unpriced_reason']);
    $chatUsage = ['quality' => 'reported', 'normalizer_version' => 'chat-completions-inclusive-v1',
      'input_tokens_total' => '10', 'input_tokens_cache_read' => NULL, 'input_tokens_cache_write' => NULL,
      'output_tokens_total' => '10', 'output_tokens_reasoning' => NULL, 'provider_total_tokens' => '20'];
    $this->assertSame('unknown_model', $this->costRating->computeRate(self::SITE, $provider, $chatUsage,
      $this->nowMs())['unpriced_reason'], 'qwen-image is only in the image book');
  }

  public function testImageCostEntriesCarryTheImageComponent(): void {
    $book = $this->priceBook->createDraft(self::SITE, PriceBookService::KIND_SUPPLIER_IMAGE, 'img-v1', 'CNY',
      json_encode(['accounts' => ['xinshi' => ['models' => ['qwen-image' => ['per_image' => 250_000]]]]]),
      $this->nowMs());
    $this->priceBook->activate($book, self::SITE, PriceBookService::KIND_SUPPLIER_IMAGE);
    $provider = ['account_ref' => 'xinshi', 'requested_model' => 'qwen-image'];

    $this->costRating->rateAttempt(self::SITE, 'att-img', 1, $provider, $this->imageUsage(2), $this->nowMs());
    $row = $this->database->select(CostRatingService::TABLE, 'c')->fields('c')
      ->condition('attempt_id', 'att-img')->execute()->fetchAssoc();
    $this->assertSame(500_000, (int) $row['image_cost_micros']);
    $this->assertSame(500_000, (int) $row['total_cost_micros']);
    $this->assertNull($row['cache_read_cost_micros']);
    $this->assertSame(CostRatingService::STATE_RATED_ESTIMATE, $row['valuation_state']);

    // Same revision, same result: idempotent. A different image count would be a conflict.
    $this->costRating->rateAttempt(self::SITE, 'att-img', 1, $provider, $this->imageUsage(2), $this->nowMs());
    $this->assertSame(1, (int) $this->database->select(CostRatingService::TABLE)->countQuery()->execute()->fetchField());
    $this->expectException(PriceBookException::class);
    $this->expectExceptionMessageMatches('/cost_conflict/');
    $this->costRating->rateAttempt(self::SITE, 'att-img', 1, $provider, $this->imageUsage(3), $this->nowMs());
  }

  public function testDifferentResultCannotOverwriteImmutableCostEntry(): void {
    $version = $this->seedRate('immutable', 'CNY', $this->sampleRates(1, 0, 0, 0));
    $this->priceBook->activate($version, self::SITE, self::KIND);
    $provider = ['account_ref' => 'xinshi', 'requested_model' => 'doc-model'];
    $firstUsage = ['quality' => 'reported', 'normalizer_version' => 'v1',
      'input_tokens_total' => '1', 'input_tokens_cache_read' => NULL,
      'input_tokens_cache_write' => NULL, 'output_tokens_total' => '0',
      'output_tokens_reasoning' => NULL, 'provider_total_tokens' => '1'];
    $secondUsage = $firstUsage;
    $secondUsage['input_tokens_total'] = '1000000';

    $this->costRating->rateAttempt(self::SITE, 'att-immutable', 1, $provider,
      $firstUsage, $this->nowMs());
    $this->expectException(PriceBookException::class);
    $this->expectExceptionMessageMatches('/cost_conflict/');
    $this->costRating->rateAttempt(self::SITE, 'att-immutable', 1, $provider,
      $secondUsage, $this->nowMs());
  }

  private function seedRate(string $version, string $currency, array $rates): int {
    $json = json_encode($rates, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $this->priceBook->createDraft(self::SITE, self::KIND, $version, $currency, $json, $this->nowMs());
  }

  /**
   * A reported image usage block as ImageUsageNormalizer produces it.
   */
  private function imageUsage(int $images, ?int $inputTokens = NULL, ?int $outputTokens = NULL): array {
    return [
      'quality' => 'reported', 'normalizer_version' => 'images-openai-v1',
      'input_tokens_total' => $inputTokens === NULL ? NULL : (string) $inputTokens,
      'input_tokens_cache_read' => NULL, 'input_tokens_cache_write' => NULL,
      'output_tokens_total' => $outputTokens === NULL ? NULL : (string) $outputTokens,
      'output_tokens_reasoning' => NULL, 'provider_total_tokens' => NULL,
      'images_generated' => (string) $images,
    ];
  }

  private function sampleRates(int $perMillionInput, int $perMillionCacheRead,
    int $perMillionCacheWrite, int $perMillionOutput): array {
    return [
      'accounts' => [
        'xinshi' => [
          'models' => [
            'doc-model' => [
              'per_million_input' => $perMillionInput,
              'per_million_cache_read' => $perMillionCacheRead,
              'per_million_cache_write' => $perMillionCacheWrite,
              'per_million_output' => $perMillionOutput,
            ],
          ],
        ],
      ],
    ];
  }

  /** The same instant the mocked TimeInterface reports, so "now" matches the services. */
  private function nowMs(): int {
    return (int) round(($this->now + 0.25) * 1000);
  }

}
