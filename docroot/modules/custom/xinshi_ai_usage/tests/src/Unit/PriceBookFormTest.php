<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormValidator;
use Drupal\Core\Form\FormSubmitter;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\xinshi_ai_usage\Contract\PriceCatalogFetcher;
use Drupal\xinshi_ai_usage\Form\PriceBookForm;
use Drupal\xinshi_ai_usage\Service\CurrencyDisplay;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Drupal\xinshi_ai_usage\Service\ReportedModels;
use Drupal\xinshi_ai_usage\Service\UsageProjectionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The price book form keys price books by the producer-registered site id,
 * refuses the unedited example rates, and can activate saved drafts.
 */
final class PriceBookFormTest extends TestCase {

  use CurrencyDisplayTestTrait;

  private const KIND = PriceBookService::KIND_SUPPLIER_CHAT;

  private Connection $database;
  private PriceBookService $priceBook;
  private ProducerVault $vault;
  private TimeInterface $time;
  private array $messages = [];
  private int $now = 1_700_000_000;
  private string $timezone = 'UTC';

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('price_form_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'price_form_test');
    $this->database->schema()->createTable(PriceBookService::TABLE, xinshi_ai_usage_schema()[PriceBookService::TABLE]);
    // Only the columns ReportedModels reads; the real schema has many more.
    $this->database->schema()->createTable(UsageProjectionService::ATTEMPT_TABLE, [
      'fields' => [
        'site_id' => ['type' => 'varchar_ascii', 'length' => 128, 'not null' => TRUE],
        'model_id' => ['type' => 'varchar_ascii', 'length' => 128, 'not null' => TRUE],
        'started_at' => ['type' => 'int', 'size' => 'big', 'not null' => TRUE],
        'occurred_at' => ['type' => 'int', 'size' => 'big', 'not null' => FALSE],
        'images_generated' => ['type' => 'int', 'not null' => FALSE],
        'payer' => ['type' => 'varchar_ascii', 'length' => 16, 'not null' => TRUE, 'default' => 'platform'],
      ],
    ]);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentMicroTime')->willReturnCallback(fn() => $this->now + 0.25);
    $this->time = $time;
    $this->priceBook = new PriceBookService($this->database, $time);
    // Settings is a static singleton the vault reads the hash salt from; initialise it
    // here so every test is self-contained regardless of run order.
    new Settings(['hash_salt' => 'isolated-hash-salt']);
    $privateKey = $this->createMock(PrivateKey::class);
    $privateKey->method('get')->willReturn('isolated-site-private-key');
    $this->vault = new ProducerVault(new KeyValueMemoryFactory(), $privateKey);

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(
      fn(TranslatableMarkup $markup) => $markup->getUntranslatedString());
    $messenger = $this->createMock(MessengerInterface::class);
    foreach (['addStatus', 'addWarning', 'addError'] as $method) {
      $messenger->method($method)->willReturnCallback(function ($message) use ($method, $messenger) {
        $this->messages[] = [$method, (string) $message];
        return $messenger;
      });
    }
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(1);
    $account->method('getTimeZone')->willReturnCallback(fn() => $this->timezone);
    $container = new ContainerBuilder();
    $container->set('string_translation', $translation);
    $container->set('messenger', $messenger);
    $container->set('current_user', $account);
    \Drupal::setContainer($container);
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    Database::removeConnection('price_form_test');
    parent::tearDown();
  }

  public function testSiteIdsComeFromRegisteredProducersNotTheCmsHost(): void {
    // No producer yet: the CMS host is a documented fallback and the form says so.
    $form = $this->form()->buildForm([], new FormState());
    $this->assertSame(['cms.example'], array_keys($form['create']['site_id']['#options']));
    $this->assertArrayHasKey('unregistered', $form['overview']);

    // Producers registered with the frontend host: that is what events carry.
    $this->vault->set('chat-node', 'secret', 'app.example', $this->now);
    $this->vault->set('image-node', 'secret', 'app.example', $this->now);
    $form = $this->form()->buildForm([], new FormState());
    $this->assertSame(['app.example'], array_keys($form['create']['site_id']['#options']));
    $this->assertArrayNotHasKey('unregistered', $form['overview']);

    // settings.php producers are included; an explicit site id setting wins over all.
    $form = $this->form(['xinshi_ai_usage.producers' => ['deploy' => ['secret' => 'x', 'site_id' => 'deploy.example']]])
      ->buildForm([], new FormState());
    $this->assertSame(['deploy.example', 'app.example'], array_keys($form['create']['site_id']['#options']));
    $form = $this->form(['xinshi_ai_usage.site_id' => 'fixed.example'])->buildForm([], new FormState());
    $this->assertSame(['fixed.example'], array_keys($form['create']['site_id']['#options']));
  }

  public function testUneditedExampleRatesAreRefused(): void {
    $this->vault->set('chat-node', 'secret', 'app.example', $this->now);
    $form = $this->form();
    $built = $form->buildForm([], new FormState());
    $state = (new FormState())->setValues([
      'site_id' => 'app.example', 'book_kind' => self::KIND, 'version' => 'v1', 'currency' => 'CNY',
      'rates_json' => $built['create']['rates_json']['#default_value'],
    ]);
    $form->validateForm($built, $state);
    $this->assertArrayHasKey('rates_json', $state->getErrors());
    $this->assertStringContainsString('示例', (string) $state->getErrors()['rates_json']);

    // The image example is refused too, whichever kind is selected.
    $state = (new FormState())->setValues([
      'site_id' => 'app.example', 'book_kind' => PriceBookService::KIND_SUPPLIER_IMAGE, 'version' => 'v1',
      'currency' => 'CNY', 'rates_json' => json_encode(PriceBookForm::seedRates(PriceBookService::KIND_SUPPLIER_IMAGE)),
    ]);
    $form->validateForm($built, $state);
    $this->assertStringContainsString('示例', (string) $state->getErrors()['rates_json']);

    // Editing the example (even to explicit zeros) is accepted.
    $rates = PriceBookForm::seedRates();
    $rates['accounts']['xinshi']['models'] = ['free-model' => ['per_million_input' => 0,
      'per_million_cache_read' => 0, 'per_million_cache_write' => 0, 'per_million_output' => 0]];
    $state = (new FormState())->setValues(['site_id' => 'app.example', 'book_kind' => self::KIND, 'version' => 'v1',
      'currency' => 'CNY', 'rates_json' => json_encode($rates)]);
    $form->validateForm($built, $state);
    $this->assertSame([], $state->getErrors());

    // The rates are validated against the selected kind: chat rates are not an image book.
    $state = (new FormState())->setValues(['site_id' => 'app.example',
      'book_kind' => PriceBookService::KIND_SUPPLIER_IMAGE, 'version' => 'v1', 'currency' => 'CNY',
      'rates_json' => json_encode($rates)]);
    $form->validateForm($built, $state);
    $this->assertStringContainsString('per_image', (string) $state->getErrors()['rates_json']);
    $state = (new FormState())->setValues(['site_id' => 'app.example', 'book_kind' => 'supplier_video',
      'version' => 'v1', 'currency' => 'CNY', 'rates_json' => json_encode($rates)]);
    $form->validateForm($built, $state);
    $this->assertArrayHasKey('book_kind', $state->getErrors());
  }

  public function testImageBooksAreSavedListedAndActivatedSeparatelyFromChatBooks(): void {
    $this->vault->set('chat-node', 'secret', 'app.example', $this->now);
    $form = $this->form();
    $built = $form->buildForm([], new FormState());
    $this->assertSame([self::KIND, PriceBookService::KIND_SUPPLIER_IMAGE],
      array_keys($built['create']['book_kind']['#options']));

    $rates = ['accounts' => ['xinshi' => ['models' => ['qwen-image-plus' => ['per_image' => 200_000]]]]];
    $state = (new FormState())->setValues(['site_id' => 'app.example',
      'book_kind' => PriceBookService::KIND_SUPPLIER_IMAGE, 'version' => 'img-v1', 'currency' => 'CNY',
      'rates_json' => json_encode($rates)]);
    $state->setTriggeringElement(['#name' => 'save_and_activate']);
    $form->submitForm($built, $state);
    $this->assertSame('addStatus', end($this->messages)[0]);
    $this->assertSame('img-v1', $this->priceBook->loadActive('app.example', PriceBookService::KIND_SUPPLIER_IMAGE)['version']);
    $this->assertNull($this->priceBook->loadActive('app.example', self::KIND), 'the chat book is untouched');

    // Overview and list show both kinds; a chat draft activates under its own kind.
    $built = $form->buildForm([], new FormState());
    $overview = array_map(fn(array $row): array => [$row[0], (string) $row[1], (string) $row[2]],
      $built['overview']['active']['#rows']);
    $this->assertSame([['app.example', '对话（按 token）', '无（该类调用全部标记为未定价）'],
      ['app.example', '图片（按张或 token）', 'img-v1']], $overview);
    $chatRates = PriceBookForm::seedRates();
    $chatRates['accounts']['xinshi']['models'] = ['real-model' => $chatRates['accounts']['xinshi']['models']['example-model']];
    $state = (new FormState())->setValues(['site_id' => 'app.example', 'book_kind' => self::KIND,
      'version' => 'chat-v1', 'currency' => 'CNY', 'rates_json' => json_encode($chatRates)]);
    $state->setTriggeringElement(['#name' => 'save_draft']);
    $form->submitForm($built, $state);
    $built = $form->buildForm([], new FormState());
    $draft = $this->priceBook->listVersions('app.example', self::KIND)[0];
    $row = $built['versions']['table']['app.example:' . $draft['id']];
    $this->assertSame('对话（按 token）', $row['kind']['#plain_text']);
    $this->assertSame(self::KIND, $row['activate']['#book_kind']);
    $activate = new FormState();
    $activate->setTriggeringElement($row['activate']);
    $form->activateVersion($built, $activate);
    $this->assertSame('chat-v1', $this->priceBook->loadActive('app.example', self::KIND)['version']);
    $this->assertSame('img-v1', $this->priceBook->loadActive('app.example', PriceBookService::KIND_SUPPLIER_IMAGE)['version']);
  }

  public function testDraftsAreListedAndCanBeActivatedLater(): void {
    $this->vault->set('chat-node', 'secret', 'app.example', $this->now);
    $form = $this->form();
    $built = $form->buildForm([], new FormState());
    $rates = PriceBookForm::seedRates();
    $rates['accounts']['xinshi']['models']['real-model'] = $rates['accounts']['xinshi']['models']['example-model'];
    unset($rates['accounts']['xinshi']['models']['example-model']);
    $state = (new FormState())->setValues(['site_id' => 'app.example', 'book_kind' => self::KIND, 'version' => 'v1',
      'currency' => 'CNY', 'rates_json' => json_encode($rates), 'source_ref' => 'quote-1']);
    $state->setTriggeringElement(['#name' => 'save_draft']);
    $form->submitForm($built, $state);
    $this->assertSame('addStatus', end($this->messages)[0]);
    $this->assertNull($this->priceBook->loadActive('app.example', self::KIND));

    // The list shows the draft with an activate button.
    $built = $form->buildForm([], new FormState());
    $versions = $this->priceBook->listVersions('app.example', self::KIND);
    $this->assertCount(1, $versions);
    $row = $built['versions']['table']['app.example:' . $versions[0]['id']];
    $this->assertSame('草稿', $row['status']['#plain_text']);
    $this->assertSame('submit', $row['activate']['#type']);

    $activate = new FormState();
    $activate->setTriggeringElement($row['activate']);
    $form->activateVersion($built, $activate);
    $this->assertSame('v1', $this->priceBook->loadActive('app.example', self::KIND)['version']);

    // Once active, the row no longer offers activation.
    $built = $form->buildForm([], new FormState());
    $row = $built['versions']['table']['app.example:' . $versions[0]['id']];
    $this->assertSame('生效中', $row['status']['#plain_text']);
    $this->assertArrayNotHasKey('#type', $row['activate']);

    // A site that is not registered cannot receive a price book.
    $state = (new FormState())->setValues(['site_id' => 'other.example', 'book_kind' => self::KIND, 'version' => 'v2',
      'currency' => 'CNY', 'rates_json' => json_encode($rates)]);
    $state->setTriggeringElement(['#name' => 'save_draft']);
    $form->submitForm($built, $state);
    $this->assertSame('addError', end($this->messages)[0]);
  }

  public function testLitellmPrefillFillsChatRatesAndSwitchesCurrencyToUsd(): void {
    $this->vault->set('chat-node', 'secret', 'app.example', $this->now);
    $form = $this->form();
    $built = $form->buildForm([], new FormState());

    // Chat book: the prefill replaces the rates textarea with USD list prices.
    $state = (new FormState())->setValues([
      'site_id' => 'app.example', 'book_kind' => self::KIND, 'version' => 'v1', 'currency' => 'CNY',
      'rates_json' => $built['create']['rates_json']['#default_value'],
      'prefill_account' => 'aliyun',
      'prefill_models' => "deepseek/deepseek-chat\r\n\nanthropic/claude-sonnet-4-5\n  deepseek/deepseek-chat  ",
    ]);
    $state->setUserInput(['rates_json' => 'old', 'currency' => 'CNY']);
    $form->prefillFromCatalog($built, $state);
    $this->assertSame('addStatus', end($this->messages)[0]);
    $this->assertTrue($state->isRebuilding());
    $decoded = json_decode($state->getUserInput()['rates_json'], TRUE);
    $this->assertSame([
      'per_million_input' => 280000,
      'per_million_cache_read' => 28000,
      'per_million_cache_write' => 0,
      'per_million_output' => 420000,
    ], $decoded['accounts']['aliyun']['models']['deepseek/deepseek-chat']);
    $this->assertSame([
      'per_million_input' => 3000000,
      'per_million_cache_read' => 300000,
      'per_million_cache_write' => 3750000,
      'per_million_output' => 15000000,
    ], $decoded['accounts']['aliyun']['models']['anthropic/claude-sonnet-4-5']);
    $this->assertSame('USD', $state->getUserInput()['currency']);
    $this->assertSame('USD', $state->getValue('currency'));

    // The prefilled book is a valid chat draft: it passes the normal checks.
    $state->setValues([
      'site_id' => 'app.example', 'book_kind' => self::KIND, 'version' => 'v1',
      'currency' => 'USD', 'rates_json' => $state->getUserInput()['rates_json'],
    ]);
    $form->validateForm($built, $state);
    $this->assertSame([], $state->getErrors());

    // Unknown or non-chat model ids abort the prefill; nothing is replaced.
    $state = (new FormState())->setValues([
      'book_kind' => self::KIND, 'prefill_account' => 'xinshi',
      'prefill_models' => "deepseek/deepseek-chat\ndoes/not-exist\ntext-embedding-3-small",
    ]);
    $state->setUserInput(['rates_json' => 'old', 'currency' => 'CNY']);
    $form->prefillFromCatalog($built, $state);
    $this->assertSame('addError', end($this->messages)[0]);
    $this->assertStringContainsString('catalog_mismatch', (string) end($this->messages)[1]);
    $this->assertStringContainsString('does/not-exist', (string) end($this->messages)[1]);
    $this->assertStringContainsString('text-embedding-3-small', (string) end($this->messages)[1]);
    $this->assertSame('old', $state->getUserInput()['rates_json']);
    $this->assertFalse($state->isRebuilding());

    // A chat model cannot be imported into an image book.
    $state = (new FormState())->setValues([
      'book_kind' => PriceBookService::KIND_SUPPLIER_IMAGE, 'prefill_account' => 'xinshi',
      'prefill_models' => 'deepseek/deepseek-chat',
    ]);
    $form->prefillFromCatalog($built, $state);
    $this->assertSame('addError', end($this->messages)[0]);

    // An empty model list or missing account is refused before any fetch.
    $state = (new FormState())->setValues([
      'book_kind' => self::KIND, 'prefill_account' => 'xinshi', 'prefill_models' => '  ',
    ]);
    $form->prefillFromCatalog($built, $state);
    $this->assertSame('addError', end($this->messages)[0]);
    $state = (new FormState())->setValues([
      'book_kind' => self::KIND, 'prefill_account' => ' ', 'prefill_models' => 'deepseek/deepseek-chat',
    ]);
    $form->prefillFromCatalog($built, $state);
    $this->assertSame('addError', end($this->messages)[0]);
  }

  public function testPrefillAutomaticallyMatchesReportedModels(): void {
    $this->vault->set('chat-node', 'secret', 'app.example', $this->now);
    $this->database->insert(UsageProjectionService::ATTEMPT_TABLE)
      ->fields(['site_id', 'model_id', 'started_at'])
      ->values(['site_id' => 'app.example', 'model_id' => 'deepseek-chat', 'started_at' => $this->now])
      ->values(['site_id' => 'app.example', 'model_id' => 'deepseek-chat', 'started_at' => $this->now])
      ->values(['site_id' => 'app.example', 'model_id' => 'anthropic/claude-sonnet-4-5',
        'started_at' => $this->now])
      ->values(['site_id' => 'app.example', 'model_id' => 'text-embedding-3-small',
        'started_at' => $this->now])
      ->values(['site_id' => 'app.example', 'model_id' => 'does/not-exist', 'started_at' => $this->now])
      ->values(['site_id' => 'other.example', 'model_id' => 'deepseek/deepseek-chat',
        'started_at' => $this->now])
      ->execute();
    $form = $this->form();
    $built = $form->buildForm([], new FormState());
    $state = (new FormState())->setValues([
      'site_id' => 'app.example', 'book_kind' => self::KIND, 'version' => 'v1', 'currency' => 'CNY',
      'rates_json' => $built['create']['rates_json']['#default_value'],
      'prefill_account' => 'aliyun', 'prefill_models' => '', 'prefill_search' => '',
      'prefill_popular' => [],
    ]);
    $state->setUserInput(['rates_json' => 'old', 'currency' => 'CNY']);
    $form->prefillFromCatalog($built, $state);

    // Reported model names become the rate keys, so no manual renaming is needed.
    $decoded = json_decode($state->getUserInput()['rates_json'], TRUE);
    $models = $decoded['accounts']['aliyun']['models'];
    $this->assertArrayHasKey('deepseek-chat', $models);
    $this->assertArrayHasKey('anthropic/claude-sonnet-4-5', $models);
    $this->assertSame(280000, $models['deepseek-chat']['per_million_input']);
    $this->assertArrayNotHasKey('text-embedding-3-small', $models, 'non-chat models are skipped');
    $this->assertArrayNotHasKey('does/not-exist', $models);
    $this->assertArrayNotHasKey('deepseek/deepseek-chat', $models, 'other sites are not included');

    $warnings = array_values(array_filter($this->messages,
      static fn(array $message): bool => $message[0] === 'addWarning'));
    $this->assertCount(2, $warnings);
    $this->assertStringContainsString('does/not-exist', $warnings[0][1]);
    $this->assertStringContainsString('未找到', $warnings[0][1]);
    $this->assertStringContainsString('text-embedding-3-small', $warnings[1][1]);
    $this->assertStringContainsString('不属于当前价卡类型或价格结构暂不支持', $warnings[1][1]);
    $status = end($this->messages);
    $this->assertSame('addStatus', $status[0]);
    $this->assertStringContainsString('2 个模型', $status[1]);
    $this->assertStringContainsString('其中 2 个', $status[1]);
  }

  public function testPrefillMergesSearchAndPopularModelsIntoTheList(): void {
    $form = $this->form();
    $built = $form->buildForm([], new FormState());
    $this->assertSame('xinshi_ai_usage.price_catalog_autocomplete',
      $built['create']['prefill']['prefill_search']['#autocomplete_route_name']);
    $this->assertArrayHasKey('deepseek/deepseek-chat',
      $built['create']['prefill']['prefill_popular']['#options']);

    $state = (new FormState())->setValues([
      'site_id' => 'cms.example', 'book_kind' => self::KIND, 'version' => 'v1', 'currency' => 'CNY',
      'rates_json' => $built['create']['rates_json']['#default_value'],
      'prefill_account' => 'aliyun', 'prefill_models' => 'deepseek/deepseek-chat',
      'prefill_search' => 'anthropic/claude-sonnet-4-5',
      'prefill_popular' => ['deepseek/deepseek-chat' => 'deepseek/deepseek-chat'],
    ]);
    $state->setUserInput(['rates_json' => 'old', 'currency' => 'CNY']);
    $form->prefillFromCatalog($built, $state);

    $models = json_decode($state->getUserInput()['rates_json'], TRUE)['accounts']['aliyun']['models'];
    $this->assertArrayHasKey('deepseek/deepseek-chat', $models);
    $this->assertArrayHasKey('anthropic/claude-sonnet-4-5', $models);
    $merged = $state->getUserInput()['prefill_models'];
    $this->assertStringContainsString('deepseek/deepseek-chat', $merged);
    $this->assertStringContainsString('anthropic/claude-sonnet-4-5', $merged);
    $this->assertSame('', $state->getUserInput()['prefill_search']);
    $this->assertSame([], $state->getUserInput()['prefill_popular']);
    $this->assertSame('addStatus', end($this->messages)[0], 'a valid search id never warns');
  }

  public function testSearchFieldRequiresACompleteUsableModelId(): void {
    $form = $this->form();
    $built = $form->buildForm([], new FormState());

    // A half-typed keyword is ignored with a warning; the prefill continues.
    $state = (new FormState())->setValues([
      'site_id' => 'cms.example', 'book_kind' => self::KIND, 'version' => 'v1', 'currency' => 'CNY',
      'rates_json' => $built['create']['rates_json']['#default_value'],
      'prefill_account' => 'xinshi', 'prefill_models' => 'deepseek/deepseek-chat',
      'prefill_search' => 'deepseek', 'prefill_popular' => [],
    ]);
    $state->setUserInput(['rates_json' => 'old', 'currency' => 'CNY']);
    $form->prefillFromCatalog($built, $state);
    $models = json_decode($state->getUserInput()['rates_json'], TRUE)['accounts']['xinshi']['models'];
    $this->assertSame(['deepseek/deepseek-chat'], array_keys($models));
    $warnings = array_values(array_filter($this->messages,
      static fn(array $message): bool => $message[0] === 'addWarning'));
    $this->assertCount(1, $warnings);
    $this->assertStringContainsString('"deepseek"', $warnings[0][1]);
    $this->assertStringContainsString('已忽略', $warnings[0][1]);
    $this->assertSame('addStatus', end($this->messages)[0]);
    $this->assertSame('deepseek/deepseek-chat', $state->getUserInput()['prefill_models']);

    // An exact but non-chat id is ignored the same way; with nothing else to
    // prefill the empty-list error is added, but the hint still names it.
    $this->messages = [];
    $state = (new FormState())->setValues([
      'site_id' => 'cms.example', 'book_kind' => self::KIND, 'version' => 'v1', 'currency' => 'CNY',
      'rates_json' => 'old', 'prefill_account' => 'xinshi', 'prefill_models' => '',
      'prefill_search' => 'text-embedding-3-small', 'prefill_popular' => [],
    ]);
    $form->prefillFromCatalog($built, $state);
    $this->assertSame('addError', end($this->messages)[0]);
    $this->assertStringContainsString('已上报的模型', end($this->messages)[1]);
    $warnings = array_values(array_filter($this->messages,
      static fn(array $message): bool => $message[0] === 'addWarning'));
    $this->assertCount(1, $warnings);
    $this->assertStringContainsString('text-embedding-3-small', $warnings[0][1]);
    $this->assertFalse($state->isRebuilding());
  }

  public function testReferencePreviewConvertsPricesWithoutChangingTheActiveBook(): void {
    $this->vault->set('chat-node', 'secret', 'app.example', $this->now);
    $book = $this->priceBook->createDraft('app.example', PriceBookService::KIND_SUPPLIER_IMAGE,
      'usd-v1', 'USD', '{"accounts":{"xinshi":{"models":{"m":{"per_image":20000}}}}}',
      $this->now * 1000);
    $this->priceBook->activate($book, 'app.example', PriceBookService::KIND_SUPPLIER_IMAGE);
    $before = $this->priceBook->loadActive('app.example', PriceBookService::KIND_SUPPLIER_IMAGE);
    $display = $this->currencyDisplay('CNY', ['rate_date' => '2026-10-23',
      'rates' => ['EUR' => '1', 'USD' => '1.2', 'CNY' => '8.4'], 'fetched_at' => $this->now]);
    $form = $this->form([], NULL, $display)->buildForm([], new FormState());
    $row = $form['overview']['reference']['table']['#rows'][0];
    $this->assertSame(['0.02 USD', '≈ 0.14 CNY'], array_slice($row, -2));
    $this->assertSame('CNY', $form['create']['currency']['#default_value']);
    $this->assertSame($before,
      $this->priceBook->loadActive('app.example', PriceBookService::KIND_SUPPLIER_IMAGE));
  }

  public function testAutomaticHistoryDoesNotRequireDateInputAndPreservesExactBoundaries(): void {
    $this->recordedTime();
    $this->publishCurrent();
    $form = $this->form(['xinshi_ai_usage.site_id' => 'app.example']);
    $state = $this->historicalState();
    $built = $form->buildForm([], $state);
    $this->assertSame('automatic', $built['create']['historical_mode']['#default_value']);
    $this->assertStringContainsString('2023-11-14 22:13:19.123',
      (string) $built['create']['historical_suggestion']['#plain_text']);
    $form->validateForm($built, $state);
    $this->assertSame([], $state->getErrors());
    $this->assertSame([1_699_999_999_123, 1_700_000_000_250], $state->get('historical_range'));
    $state->setTriggeringElement(['#name' => 'save_and_activate']);
    $form->submitForm($built, $state);
    $version = $this->priceBook->loadActive('app.example', self::KIND, 1_699_999_999_123);
    $this->assertSame('history', $version['version']);
    $this->assertSame(1_700_000_000_250, $version['effective_to']);
  }

  public function testCustomDateControlsUseUserTimezoneAndKeepUneditedMilliseconds(): void {
    $this->timezone = 'Asia/Shanghai';
    $this->recordedTime();
    $this->publishCurrent();
    $form = $this->form(['xinshi_ai_usage.site_id' => 'app.example']);
    $state = $this->historicalState('custom');
    $built = $form->buildForm([], $state);
    foreach (['historical_from', 'historical_to'] as $key) {
      $element = $built['create'][$key];
      $element['#value'] = $element['#default_value'];
      $element = \Drupal\Core\Render\Element\Date::preRenderDate($element);
      $this->assertSame('datetime-local', $element['#attributes']['type']);
      $state->setValue($key, $element['#value']);
    }
    $this->assertSame('2023-11-15T06:13:19', $state->getValue('historical_from'));
    $this->assertSame('2023-11-15T06:13:20', $state->getValue('historical_to'));
    $form->validateForm($built, $state);
    $this->assertSame([], $state->getErrors());
    $this->assertSame([1_699_999_999_123, 1_700_000_000_250], $state->get('historical_range'));
  }

  public function testCustomTimeAcceptsMinutesWithoutSecondsOrTimezoneSuffix(): void {
    $this->timezone = 'Asia/Shanghai';
    $form = $this->form(['xinshi_ai_usage.site_id' => 'app.example']);
    $state = $this->historicalState('custom')->setValue('historical_from', '2023-11-15T06:12')
      ->setValue('historical_to', '2023-11-15T06:13');
    $built = $form->buildForm([], $state);
    $form->validateForm($built, $state);
    $this->assertSame([], $state->getErrors());
    $this->assertSame([1_699_999_920_000, 1_699_999_980_000], $state->get('historical_range'));
  }

  public function testAutomaticEndDoesNotMoveWhileTheUserFillsTheForm(): void {
    $this->recordedTime();
    $form = $this->form(['xinshi_ai_usage.site_id' => 'app.example']);
    $state = $this->historicalState();
    $built = $form->buildForm([], $state);
    $this->now += 10;
    $form->validateForm($built, $state);
    $this->assertSame([], $state->getErrors());
    $this->assertSame([1_699_999_999_123, 1_700_000_000_250], $state->get('historical_range'));
  }

  public function testChangedHistoryOrPriceBookRequiresReviewingTheSuggestedRange(): void {
    $this->recordedTime();
    $this->publishCurrent();
    $form = $this->form(['xinshi_ai_usage.site_id' => 'app.example']);
    $state = $this->historicalState();
    $built = $form->buildForm([], $state);
    $this->recordedTime(1_699_999_998_123);
    $form->validateForm($built, $state);
    $this->assertArrayHasKey('historical_mode', $state->getErrors());
    $this->assertNull($state->get('historical_range'));
    $state = $this->historicalState();
    $built = $form->buildForm([], $state);
    $id = $this->priceBook->createDraft('app.example', self::KIND, 'covered', 'CNY',
      $state->getValue('rates_json'), 1_699_999_998_123, 'contract', '1', 1_700_000_000_250);
    $this->priceBook->activate($id, 'app.example', self::KIND, '1');
    $form->validateForm($built, $state);
    $this->assertArrayHasKey('historical_mode', $state->getErrors());
    $this->assertNull($state->get('historical_range'));
  }

  public function testAutomaticRangeFollowsSelectedSiteAndPriceBookKind(): void {
    $this->recordedTime();
    $this->recordedTime(1_699_999_997_456, PriceBookService::KIND_SUPPLIER_IMAGE);
    $this->database->insert(UsageProjectionService::ATTEMPT_TABLE)->fields(['site_id' => 'other',
      'model_id' => 'm', 'started_at' => 0, 'occurred_at' => 1])->execute();
    $form = $this->form(['xinshi_ai_usage.site_id' => 'app.example']);
    $state = $this->historicalState()->setValue('book_kind', PriceBookService::KIND_SUPPLIER_IMAGE);
    $built = $form->buildForm([], $state);
    $this->assertSame(1_699_999_997_456, $state->get('historical_suggestion')['range'][0]);
    $refreshed = $form->refreshCreate($built, $state);
    $this->assertArrayHasKey('historical_suggestion', $refreshed);
    $this->assertSame(['book_kind' => PriceBookService::KIND_SUPPLIER_IMAGE],
      $refreshed['prefill']['prefill_search']['#autocomplete_route_parameters']);
  }

  public function testNoHistoryProvidesAClearManualAlternative(): void {
    $form = $this->form(['xinshi_ai_usage.site_id' => 'app.example']);
    $state = $this->historicalState();
    $built = $form->buildForm([], $state);
    $this->assertStringContainsString('自定义', (string) $built['create']['historical_suggestion']['#plain_text']);
    $form->validateForm($built, $state);
    $this->assertArrayHasKey('historical_mode', $state->getErrors());
    $this->assertNull($state->get('historical_range'));
  }

  public function testCustomDateErrorsIdentifyTheFieldAndCause(): void {
    $form = $this->form(['xinshi_ai_usage.site_id' => 'app.example']);
    foreach ([['2023-02-30T22:13', '2023-11-14T22:13', 'historical_from'],
      ["2023-11-14T22:12\0", '2023-11-14T22:13', 'historical_from'],
      ['1969-12-31T22:12', '2023-11-14T22:13', 'historical_from'],
      ['2023-11-14T22:12', '2023-11-14T22:13:21', 'historical_to'],
      ['2023-11-14T22:13', '2023-11-14T22:12', 'historical_to']] as [$from, $to, $field]) {
      $state = $this->historicalState('custom')->setValue('historical_from', $from)
        ->setValue('historical_to', $to)->setValue('source_ref', '');
      $built = $form->buildForm([], $state);
      $form->validateForm($built, $state);
      $this->assertArrayHasKey($field, $state->getErrors());
      $this->assertArrayHasKey('source_ref', $state->getErrors());
      $this->assertNull($state->get('historical_range'));
    }
  }

  public function testHistoricalPublicationExplainsOverlapAndRepeatedVersion(): void {
    $this->recordedTime();
    $this->publishCurrent();
    $this->now += 10;
    $form = $this->form(['xinshi_ai_usage.site_id' => 'app.example']);
    $state = $this->historicalState('custom')->setValue('historical_from', '2023-11-14T22:13:19')
      ->setValue('historical_to', '2023-11-14T22:13:21');
    $built = $form->buildForm([], $state);
    $form->validateForm($built, $state);
    $this->assertSame([], $state->getErrors());
    $state->setTriggeringElement(['#name' => 'save_and_activate']);
    $form->submitForm($built, $state);
    $this->assertStringContainsString('草稿已保存', end($this->messages)[1]);
    $this->assertStringContainsString('重叠', end($this->messages)[1]);
    $this->assertSame('current', $this->priceBook->loadActive('app.example', self::KIND)['version']);
    $form->submitForm($built, $state);
    $this->assertStringContainsString('新的版本号', end($this->messages)[1]);
    $this->assertCount(2, $this->priceBook->listVersions('app.example', self::KIND));
  }

  public function testAjaxSelectorsPreserveTypedFieldsWithoutSavingThroughFormApi(): void {
    $this->recordedTime();
    $this->publishCurrent();
    $form = $this->form(['xinshi_ai_usage.site_id' => 'app.example']);
    $builder = $this->formBuilder();
    foreach (['site_id', 'book_kind', 'historical', 'historical_mode'] as $trigger) {
      $state = (new FormState())->setFormObject($form)->setRequestMethod('POST');
      $state->clearErrors();
      $built = $form->buildForm([], $state);
      $builder->prepareForm($form->getFormId(), $built, $state);
      $input = $this->historicalState('custom')->getValues() + [
        'form_id' => $form->getFormId(), '_triggering_element_name' => $trigger,
        'historical_from' => '2023-11-14T22:12', 'historical_to' => '2023-11-14T22:13',
      ];
      // Selector changes must also work while required fields are incomplete.
      $input['version'] = '';
      $input['source_ref'] = '';
      $state->setUserInput($input);
      $builder->processForm($form->getFormId(), $built, $state);
      $refreshed = $form->refreshCreate($built, $state);
      $this->assertSame($trigger, $state->getTriggeringElement()['#name']);
      $this->assertFalse($state->isSubmitted());
      $this->assertTrue($state->isRebuilding());
      $this->assertSame([], $state->getErrors());
      foreach (['version', 'rates_json', 'source_ref', 'historical_from', 'historical_to'] as $key) {
        $this->assertSame($input[$key], $refreshed[$key]['#value']);
      }
      $this->assertCount(1, $this->priceBook->listVersions('app.example', self::KIND));
      $this->assertSame([], $this->messages);
    }
  }

  private function formBuilder(): FormBuilder {
    $requests = new RequestStack();
    $requests->push(Request::create('https://cms.example/admin/config/xinshi/ai/usage/pricing', 'POST'));
    $container = \Drupal::getContainer();
    $container->set('request_stack', $requests);
    $resolver = $this->createMock(\Drupal\Core\DependencyInjection\ClassResolverInterface::class);
    $callables = new \Drupal\Core\Utility\CallableResolver($resolver);
    $urls = $this->createMock(\Drupal\Core\Routing\UrlGeneratorInterface::class);
    $urls->method('generateFromRoute')->willReturnCallback(static fn($name, $params, $options, $metadata)
      => $metadata ? (new \Drupal\Core\GeneratedUrl())->setGeneratedUrl('/pricing') : '/pricing');
    $container->set('url_generator', $urls);
    $access = $this->createMock(\Drupal\Core\Access\AccessManagerInterface::class);
    $access->method('checkNamedRoute')->willReturn(\Drupal\Core\Access\AccessResult::allowed());
    $container->set('access_manager', $access);
    $elements = $this->createMock(\Drupal\Core\Render\ElementInfoManagerInterface::class);
    $elements->method('getInfo')->willReturnCallback(static function (string $type): array {
      $class = '\\Drupal\\Core\\Render\\Element\\' . ucfirst($type);
      if (!class_exists($class)) {
        return [];
      }
      $element = new $class([], $type, []);
      $info = $element->getInfo() + ['#type' => $type];
      if ($element instanceof \Drupal\Core\Render\Element\FormElementInterface) {
        $info['#input'] = TRUE;
        $info['#value_callback'] = [$class, 'valueCallback'];
      }
      return $info;
    });
    $csrf = $this->createMock(\Drupal\Core\Access\CsrfTokenGenerator::class);
    $validator = new FormValidator($requests, $container->get('string_translation'), $csrf,
      $this->createMock(\Psr\Log\LoggerInterface::class),
      $this->createMock(\Drupal\Core\Form\FormErrorHandlerInterface::class), $callables);
    $submitter = new FormSubmitter($requests, $urls,
      $this->createMock(\Drupal\Core\EventSubscriber\RedirectResponseSubscriber::class), $callables);
    return new FormBuilder($validator, $submitter,
      $this->createMock(\Drupal\Core\Form\FormCacheInterface::class),
      $this->createMock(\Drupal\Core\Extension\ModuleHandlerInterface::class),
      $this->createMock(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class),
      $requests, $resolver, $elements,
      $this->createMock(\Drupal\Core\Theme\ThemeManagerInterface::class), $csrf, $callables);
  }

  private function recordedTime(int $ms = 1_699_999_999_123, string $kind = self::KIND): void {
    $this->database->insert(UsageProjectionService::ATTEMPT_TABLE)->fields([
      'site_id' => 'app.example', 'model_id' => 'model-a', 'started_at' => $ms,
      'occurred_at' => $ms, 'images_generated' => $kind === self::KIND ? NULL : 1,
    ])->execute();
  }

  private function publishCurrent(): void {
    $id = $this->priceBook->createDraft('app.example', self::KIND, 'current', 'CNY',
      $this->historicalState()->getValue('rates_json'), $this->now * 1000 + 250);
    $this->priceBook->activate($id, 'app.example', self::KIND, '1');
  }

  private function historicalState(string $mode = 'automatic'): FormState {
    return (new FormState())->setValues(['site_id' => 'app.example', 'book_kind' => self::KIND,
      'version' => 'history', 'currency' => 'CNY', 'source_ref' => 'contract', 'historical' => TRUE,
      'historical_mode' => $mode, 'rates_json' => '{"accounts":{"xinshi":{"models":{"model-a":{'
        . '"per_million_input":1,"per_million_cache_read":0,"per_million_cache_write":0,"per_million_output":1}}}}}',
    ]);
  }

  private function form(array $settings = [], ?PriceCatalogFetcher $catalog = NULL,
    ?CurrencyDisplay $display = NULL): PriceBookForm {
    $requests = new RequestStack();
    $requests->push(Request::create('https://cms.example/admin/config/xinshi/ai/usage/pricing'));
    return new PriceBookForm($this->priceBook, $catalog ?? $this->fixtureCatalog(), $this->vault,
      new ReportedModels($this->database), $requests,
      new Settings($settings + ['hash_salt' => 'isolated-hash-salt']), $this->time,
      $display ?? $this->currencyDisplay());
  }

  public function testImagePrefillCanBeReviewedSavedAndActivated(): void {
    $this->vault->set('image-cms', 'secret', 'app.example', $this->now);
    $this->database->insert(UsageProjectionService::ATTEMPT_TABLE)
      ->fields(['site_id', 'model_id', 'started_at'])
      ->values(['site_id' => 'app.example', 'model_id' => 'GPT_IMAGE_2', 'started_at' => $this->now])
      ->values(['site_id' => 'app.example', 'model_id' => 'deepseek/deepseek-chat', 'started_at' => $this->now])
      ->execute();
    $form = $this->form();
    $kind = PriceBookService::KIND_SUPPLIER_IMAGE;
    $state = (new FormState())->setValues([
      'site_id' => 'app.example', 'book_kind' => $kind, 'version' => 'image-v1',
      'currency' => 'CNY', 'prefill_account' => 'xinshi', 'prefill_models' => '',
      'prefill_search' => 'fixed-image', 'prefill_popular' => [],
    ]);
    $state->setUserInput(['rates_json' => 'old', 'currency' => 'CNY']);
    $built = $form->buildForm([], $state);
    $prefill = $form->refreshCreate($built, $state)['prefill'];
    $this->assertSame(['book_kind' => $kind], $prefill['prefill_search']['#autocomplete_route_parameters']);
    $this->assertArrayHasKey('gpt-image-2', $prefill['prefill_popular']['#options']);
    $this->assertArrayNotHasKey('deepseek/deepseek-chat', $prefill['prefill_popular']['#options']);

    $form->prefillFromCatalog($built, $state);
    $this->assertTrue($state->isRebuilding());
    $this->assertSame('USD', $state->getValue('currency'));
    $models = json_decode($state->getValue('rates_json'), TRUE)['accounts']['xinshi']['models'];
    $this->assertSame(['per_image' => 0, 'per_million_input' => 5_000_000,
      'per_million_input_image' => 8_000_000, 'per_million_output' => 30_000_000], $models['GPT_IMAGE_2']);
    $this->assertSame(['per_image' => 40_000], $models['fixed-image']);
    $this->assertArrayNotHasKey('deepseek/deepseek-chat', $models);
    $this->assertSame([], $this->priceBook->listVersions('app.example', $kind), 'Prefill never saves a draft.');

    $form->validateForm($built, $state);
    $this->assertSame([], $state->getErrors());
    $state->setTriggeringElement(['#name' => 'save_and_activate']);
    $form->submitForm($built, $state);
    $active = $this->priceBook->loadActive('app.example', $kind);
    $this->assertSame('image-v1', $active['version']);
    $this->assertSame($models, json_decode($active['rates_json'], TRUE)['accounts']['xinshi']['models']);
  }

  public function testImagePopularChoiceWorksWithoutReportedUsage(): void {
    $form = $this->form();
    $state = (new FormState())->setValues([
      'site_id' => 'cms.example', 'book_kind' => PriceBookService::KIND_SUPPLIER_IMAGE,
      'prefill_account' => 'xinshi', 'prefill_popular' => ['gpt-image-2' => 'gpt-image-2'],
    ]);
    $built = $form->buildForm([], $state);
    $form->prefillFromCatalog($built, $state);
    $this->assertSame('gpt-image-2', $state->getUserInput()['prefill_models']);
    $this->assertSame([], $state->getUserInput()['prefill_popular']);
    $this->assertSame('addStatus', end($this->messages)[0]);
  }

  /**
   * Fixed LiteLLM-shaped catalog with real price magnitudes.
   */
  private function fixtureCatalog(): PriceCatalogFetcher {
    return new class implements PriceCatalogFetcher {

      public function fetchCatalog(): array {
        return json_decode('{
          "deepseek/deepseek-chat": {"mode": "chat", "input_cost_per_token": 0.00000028,
            "output_cost_per_token": 0.00000042, "cache_read_input_token_cost": 0.000000028,
            "cache_creation_input_token_cost": 0.0},
          "anthropic/claude-sonnet-4-5": {"mode": "chat", "input_cost_per_token": 0.000003,
            "output_cost_per_token": 0.000015, "cache_read_input_token_cost": 0.0000003,
            "cache_creation_input_token_cost": 0.00000375},
          "text-embedding-3-small": {"mode": "embedding", "input_cost_per_token": 0.00000002},
          "gpt-image-2": {"mode": "image_generation", "input_cost_per_token": 0.000005,
            "input_cost_per_image_token": 0.000008, "output_cost_per_image_token": 0.00003},
          "fixed-image": {"mode": "image_edit", "output_cost_per_image": 0.04}
        }', TRUE);
      }

    };
  }

}
