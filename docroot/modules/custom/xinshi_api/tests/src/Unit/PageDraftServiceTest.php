<?php

namespace Drupal\Tests\xinshi_api\Unit;

use Drupal\block_content\BlockContentInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\Php as Uuid;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityAccessControlHandlerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\filter\FilterFormatInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Layout\LayoutDefinition;
use Drupal\Core\Layout\LayoutInterface;
use Drupal\panelizer\PanelizerInterface;
use Drupal\panels\PanelsDisplayManagerInterface;
use Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\xinshi_api\Controller\PageDraftController;
use Drupal\xinshi_api\Controller\PanelsIPEPageController;
use Drupal\xinshi_api\PageDraftException;
use Drupal\xinshi_api\PageDraftService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Yaml\Yaml;

/** Tests draft transactions and builder page JSON with isolated entities and Panels storage. */
final class PageDraftServiceTest extends TestCase {

  private Connection $database;
  private array $services;
  private array $created = [];
  private array $grants = ['node' => TRUE, 'block_content' => TRUE, 'format' => TRUE];
  private bool $hasLayout = TRUE;
  private bool $failNodeSave = FALSE;
  private bool $failNodeDelete = FALSE;
  private ?int $latestRevision = NULL;
  private array $entityGrants = ['view' => TRUE, 'update' => TRUE, 'delete' => TRUE];
  private bool $failLayoutSave = FALSE;
  private int $blockSaves = 0;
  private ?int $failBlockSaveAt = NULL;
  private int $nextId = 0;
  private string $uid = '7';

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('draft_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'draft_test');
    $this->database->schema()->createTable('xinshi_page_draft_operation',
      xinshi_api_schema()['xinshi_page_draft_operation']);
    $this->database->schema()->createTable('draft_test_entity', [
      'fields' => ['id' => ['type' => 'int', 'not null' => TRUE],
        'type' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
        'data' => ['type' => 'blob', 'size' => 'big'] ],
      'primary key' => ['id'],
    ]);

    $this->database->schema()->createTable('node', [
      'fields' => ['nid' => ['type' => 'int', 'not null' => TRUE]], 'primary key' => ['nid'],
    ]);

    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $storages = [];
    $access = [];
    foreach (['node', 'block_content'] as $type) {
      $storages[$type] = $this->createMock($type === 'node' ? NodeStorageInterface::class : EntityStorageInterface::class);
      $storages[$type]->method('create')->willReturnCallback(
        fn(array $values) => $this->entity($type, $values));
      $storages[$type]->method('load')->willReturnCallback(
        fn($id) => $this->stored($id) ? $this->created[$id]['entity'] : NULL);
      $storages[$type]->method('loadByProperties')->willReturnCallback(function ($properties) use ($type) {
        return array_column(array_filter($this->created, fn($record) =>
          $record['type'] === $type && $this->stored($record['entity']->id()) &&
          $record['entity']->uuid() === $properties['uuid']), 'entity');
      });
      $storages[$type]->method('resetCache')->willReturnCallback(function () use ($type) {
        foreach ($this->created as $id => $record) {
          if ($record['type'] === $type && ($stored = $this->stored($id))) {
            $this->created[$id]['values'] = unserialize($stored);
          }
        }
      });
      if ($type === 'node') {
        $storages[$type]->method('getLatestRevisionId')->willReturnCallback(
          fn($id) => $this->latestRevision ?? $this->created[$id]['values']['vid']);
      }
      $access[$type] = $this->createMock(EntityAccessControlHandlerInterface::class);
      $access[$type]->method('createAccess')->willReturnCallback(fn() => $this->grants[$type]);
    }
    $format = $this->createMock(FilterFormatInterface::class);
    $format->method('access')->willReturnCallback(fn() => $this->grants['format']);
    $storages['filter_format'] = $this->createMock(EntityStorageInterface::class);
    $storages['filter_format']->method('load')->with('json')->willReturn($format);
    $view_display = $this->createMock(EntityViewDisplayInterface::class);
    $view_display->method('get')->with('third_party_settings')
      ->willReturn(['panelizer' => ['enable' => TRUE]]);
    $storages['entity_view_display'] = $this->createMock(EntityStorageInterface::class);
    $storages['entity_view_display']->method('load')->willReturn($view_display);
    $entities->method('getStorage')->willReturnCallback(fn($type) => $storages[$type]);
    $entities->method('getAccessControlHandler')->willReturnCallback(fn($type) => $access[$type]);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturnCallback(fn() => $this->uid);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $language = new Language(['id' => 'en']);
    $languages = $this->createMock(LanguageManagerInterface::class);
    $languages->method('getDefaultLanguage')->willReturn($language);
    $languages->method('getCurrentLanguage')->willReturn($language);
    $languages->method('getLanguage')->willReturnCallback(fn($id) => $id === 'en' ? $language : NULL);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1234567890);
    $panelizer = $this->createMock(PanelizerInterface::class);
    $panelizer->method('getPanelizerSettings')->willReturn(['custom' => TRUE, 'allow' => TRUE]);
    $panelizer->method('setPanelsDisplay')->willReturnCallback(function ($node, $mode, $default, $display) {
      if ($this->failLayoutSave) {
        throw new \RuntimeException('Injected layout failure');
      }
      $node->set('panelizer', [['panels_display' => $display->getConfiguration()]]);
      $node->save();
    });
    $panels = $this->createMock(PanelsDisplayManagerInterface::class);
    $makeDisplay = function (array $configuration = ['blocks' => []]) {
      $layout = $this->createMock(LayoutInterface::class);
      $layout->method('getPluginDefinition')->willReturn(new LayoutDefinition([
        'regions' => ['content' => ['label' => 'Content']],
      ]));
      $display = $this->createMock(PanelsDisplayVariant::class);
      $display->method('getLayout')->willReturn($layout);
      $display->method('getConfiguration')->willReturnCallback(function () use (&$configuration) {
        return $configuration;
      });
      $display->method('setConfiguration')->willReturnCallback(function ($value) use (&$configuration) {
        $configuration = $value;
      });
      $display->method('addBlock')->willReturnCallback(function ($block) use (&$configuration) {
        $id = (new Uuid())->generate();
        $configuration['blocks'][$id] = $block + ['provider' => 'block_content'];
        return $id;
      });
      return $display;
    };
    $panels->method('createDisplay')->willReturnCallback(fn() => $makeDisplay());
    $panels->method('importDisplay')->willReturnCallback(fn($configuration) => $makeDisplay($configuration));
    $panelizer->method('getPanelsDisplay')->willReturnCallback(fn($node) =>
      $makeDisplay($this->created[$node->id()]['values']['panelizer'][0]['panels_display']));
    $modules = $this->createMock(ModuleHandlerInterface::class);
    $modules->method('moduleExists')->with('panelizer')->willReturn(TRUE);
    $this->services = ['database' => $this->database, 'entity_type.manager' => $entities,
      'current_user' => $account, 'language_manager' => $languages,
      'datetime.time' => $time, 'panelizer' => $panelizer, 'panels.display_manager' => $panels,
      'module_handler' => $modules];
    $token = $this->createMock(Token::class);
    $token->method('replace')->willReturn('Approved page');
    $container = new ContainerBuilder();
    foreach ($this->services + ['token' => $token] as $name => $service) {
      $container->set($name, $service);
    }
    \Drupal::setContainer($container);
  }

  protected function tearDown(): void {
    Database::removeConnection('draft_test');
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  private function drafts(): PageDraftService {
    $module = dirname(__DIR__, 3);
    $definition = Yaml::parseFile($module . '/xinshi_api.services.yml')
      ['services']['xinshi_api.page_drafts'];
    $arguments = array_map(function (string $reference) {
      $name = ltrim($reference, '@?');
      if (!str_starts_with($reference, '@?')) {
        self::assertArrayHasKey($name, $this->services);
      }
      return $this->services[$name] ?? NULL;
    }, $definition['arguments']);
    return new ($definition['class'])(...$arguments);
  }

  private function entity(string $type, array $values): NodeInterface|BlockContentInterface {
    $id = ++$this->nextId;
    $values['vid'] = $id * 10;
    $entity = $this->createMock($type === 'node' ? Node::class : BlockContentInterface::class);
    $this->created[$id] = ['entity' => $entity, 'type' => $type, 'values' => $values];
    $entity->method('id')->willReturn($id);
    $entity->method('uuid')->willReturn((new Uuid())->generate());
    $entity->method('label')->willReturnCallback(
      fn() => $this->created[$id]['values']['title'] ?? $this->created[$id]['values']['info']);
    $entity->method('bundle')->willReturn($values['type']);
    $entity->method('getEntityTypeId')->willReturn($type);
    $entity->method('language')->willReturn(new Language(['id' => $values['langcode']]));
    $entity->method('getRevisionId')->willReturnCallback(fn() => $this->created[$id]['values']['vid']);
    $entity->method('toArray')->willReturnCallback(fn() => $this->created[$id]['values']);
    $entity->method('isNew')->willReturnCallback(fn() => !$this->stored($id));
    $entity->method('setNewRevision')->willReturnCallback(function () use ($id) {
      $this->created[$id]['newRevision'] = TRUE;
    });
    if ($type === 'node') {
      $entity->method('getOwnerId')->willReturnCallback(fn() => $this->created[$id]['values']['uid']);
      $entity->method('getTranslationLanguages')->willReturnCallback(fn() =>
        [$values['langcode'] => new Language(['id' => $values['langcode']])] +
        array_map(fn($translation) => $translation->language(), $this->created[$id]['translations'] ?? []));
      $entity->method('getTranslation')->willReturnCallback(
        fn($langcode) => $this->created[$id]['translations'][$langcode] ?? $entity);
      $entity->method('delete')->willReturnCallback(function () use ($id) {
        $this->database->delete('draft_test_entity')->condition('id', $id)->execute();
        $this->database->delete('node')->condition('nid', $id)->execute();
        if ($this->failNodeDelete) {
          throw new \RuntimeException('Injected node delete failure');
        }
      });
    }
    $entity->method('validate')->willReturn(new ConstraintViolationList());
    $entity->method('hasField')->with('panelizer')->willReturnCallback(fn() => $this->hasLayout);
    if ($type === 'node') {
      $field = $this->createMock(FieldItemListInterface::class);
      $field->method('getValue')->willReturnCallback(
        fn() => $this->created[$id]['values']['panelizer'] ?? []);
      $entity->method('get')->with('panelizer')->willReturn($field);
    }
    else {
      $field = $this->createMock(FieldItemListInterface::class);
      $field->method('__get')->with('value')->willReturnCallback(
        fn() => $this->created[$id]['values']['body']['value']);
      $entity->method('get')->with('body')->willReturn($field);
    }
    $entity->method('access')->willReturnCallback(fn($operation) => $this->entityGrants[$operation]);
    $entity->method('isPublished')->willReturnCallback(fn() => $this->created[$id]['values']['status']);
    $entity->method('set')->willReturnCallback(function ($name, $value) use ($entity, $id) {
      $this->created[$id]['values'][$name] = $value;
      return $entity;
    });
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('/node/' . $id);
    $entity->method('toUrl')->willReturn($url);
    $entity->method('save')->willReturnCallback(function () use ($type, $id) {
      if (!empty($this->created[$id]['newRevision'])) {
        $this->created[$id]['values']['vid']++;
        $this->created[$id]['newRevision'] = FALSE;
      }
      $this->database->merge('draft_test_entity')->key('id', $id)->fields([
        'type' => $type, 'data' => serialize($this->created[$id]['values']),
      ])->execute();
      if ($type === 'node') {
        $this->database->merge('node')->key('nid', $id)->fields(['nid' => $id])->execute();
      }
      if ($type === 'block_content' && ++$this->blockSaves === $this->failBlockSaveAt) {
        throw new \RuntimeException('Injected block save failure');
      }
      if ($type === 'node' && $this->failNodeSave) {
        throw new \RuntimeException('Injected node save failure');
      }
      return 1;
    });
    return $entity;
  }

  private function stored($id): string|false {
    return $this->database->select('draft_test_entity', 'e')->fields('e', ['data'])
      ->condition('id', $id)->execute()->fetchField();
  }

  private function input(): \stdClass {
    return (object) ['title' => 'Approved page',
      'body' => [(object) ['type' => 'text', 'body' => '已批准的内容']]];
  }

  private function countRows(string $table): int {
    return (int) $this->database->select($table)->countQuery()->execute()->fetchField();
  }

  private function refuses(callable $operation, string $reason): void {
    try {
      $operation();
      self::fail('Expected a draft refusal');
    }
    catch (PageDraftException $e) {
      self::assertSame($reason, $e->reason);
    }
  }

  public function testCapabilityDoesNotWrite(): void {
    self::assertTrue($this->drafts()->canCreate());
    self::assertSame(0, $this->countRows('xinshi_page_draft_operation'));
    self::assertSame(0, $this->countRows('draft_test_entity'));
  }

  public function testCreatesUnpublishedPanelizerPageWithPinnedBlockRevision(): void {
    $input = $this->input();
    $result = $this->drafts()->createDraft((new Uuid())->generate(), $input);
    self::assertSame('draft', $result['result']['status']);
    self::assertFalse($this->database->inTransaction());
    $node = $this->created[$result['result']['id']]['values'];
    self::assertFalse($node['status']);
    self::assertSame($this->uid, $node['uid']);
    $components = $node['panelizer'][0]['panels_display']['blocks'];
    self::assertCount(1, $components);
    $configuration = reset($components);
    self::assertSame('content', $configuration['region']);
    self::assertSame(0, $configuration['weight']);
    self::assertSame('block_content', $configuration['provider']);
    $blocks = array_filter($this->created, fn($record) => $record['type'] === 'block_content');
    $block = reset($blocks);
    self::assertSame('block_content:' . $block['entity']->uuid(), $configuration['id']);
    self::assertSame($block['entity']->getRevisionId(), $configuration['vid']);
    self::assertFalse($block['values']['status']);
    self::assertEquals($input->body[0], json_decode($block['values']['body']['value']));
    self::assertSame(2, $this->countRows('draft_test_entity'));
    self::assertSame(1, $this->countRows('xinshi_page_draft_operation'));
  }

  public static function componentProvider(): array {
    $text = ['spacer' => 'md', 'type' => 'text', 'body' => '测试成功', 'animate' => FALSE];
    return [
      'single component' => [[$text]],
      'several components with a nested layout' => [[
        $text,
        ['type' => 'layout-builder', 'elements' => [['elements' => [$text]]]],
        ['type' => 'text', 'body' => '最后一个组件'],
      ]],
    ];
  }

  #[DataProvider('componentProvider')]
  public function testDraftRoundTripsThroughBuilderPageJson(array $body): void {
    $input = (object) ['title' => '组件草稿测试', 'body' => json_decode(json_encode($body))];
    $drafts = $this->drafts();
    $id = (new Uuid())->generate();
    $saved = $drafts->createDraft($id, $input);
    $node = $this->created[$saved['result']['id']]['entity'];
    // This read path does not use the Panels IPE editing services from the constructor.
    $controller = $this->getMockBuilder(PanelsIPEPageController::class)
      ->disableOriginalConstructor()->onlyMethods([])->getMock();
    $response = $controller->landingPageCanonical($node);
    $page = json_decode($response->getContent(), TRUE, 64, JSON_THROW_ON_ERROR);
    self::assertTrue($page['status']);
    self::assertCount(count($body), $page['body']);
    self::assertSame($body, array_column(array_column($page['body'], 'attributes'), 'body'));
    self::assertCount(count($body), array_unique(array_column($page['body'], 'uuid')));
    foreach ($page['body'] as $block) {
      self::assertSame('json', $block['type']);
      self::assertArrayHasKey('type', $block['attributes']['body']);
      self::assertArrayNotHasKey(0, $block['attributes']['body']);
    }
    self::assertEquals($saved, $drafts->createDraft($id, $input));
    self::assertEquals($saved, $drafts->findDraft($id));
    self::assertSame(count($body) + 1, $this->countRows('draft_test_entity'));
  }

  public function testRetryAndLookupReuseCommittedOperation(): void {
    $id = (new Uuid())->generate();
    $drafts = $this->drafts();
    $input = $this->input();
    $first = $drafts->createDraft($id, $input);
    $reordered = (object) ['body' => $input->body, 'title' => $input->title];
    self::assertEquals($first, $drafts->createDraft($id, $reordered));
    self::assertEquals($first, $this->drafts()->findDraft($id));
    $input->title = 'Changed';
    $this->refuses(fn() => $drafts->createDraft($id, $input), 'operation_conflict');
    self::assertSame(2, $this->countRows('draft_test_entity'));
    self::assertSame(1, $this->countRows('xinshi_page_draft_operation'));
    $this->uid = '8';
    $this->refuses(fn() => $drafts->findDraft($id), 'not_found');
  }

  public static function permissionProvider(): array {
    return [['node'], ['block_content'], ['format']];
  }

  #[DataProvider('permissionProvider')]
  public function testRevocationRefusesNewWritesButPreservesRecovery(string $permission): void {
    $drafts = $this->drafts();
    $id = (new Uuid())->generate();
    $saved = $drafts->createDraft($id, $this->input());
    $this->grants[$permission] = FALSE;
    self::assertFalse($drafts->canCreate());
    $this->refuses(fn() => $drafts->createDraft((new Uuid())->generate(), $this->input()),
      'permission_denied');
    self::assertEquals($saved, $drafts->findDraft($id));
    self::assertSame(2, $this->countRows('draft_test_entity'));
    self::assertSame(1, $this->countRows('xinshi_page_draft_operation'));
  }

  public function testMissingLayoutRefusesWithoutLeavingAReservation(): void {
    $this->hasLayout = FALSE;
    self::assertFalse($this->drafts()->canCreate());
    $this->refuses(fn() => $this->drafts()->createDraft((new Uuid())->generate(), $this->input()),
      'draft_not_supported');
    self::assertSame(0, $this->countRows('draft_test_entity'));
    self::assertSame(0, $this->countRows('xinshi_page_draft_operation'));
  }

  public static function failureProvider(): array {
    return [['layout'], ['node'], ['second block']];
  }

  #[DataProvider('failureProvider')]
  public function testPartialEntityWritesAndReservationRollBackTogether(string $failure): void {
    $input = $this->input();
    if ($failure === 'layout') {
      $this->failLayoutSave = TRUE;
    }
    elseif ($failure === 'node') {
      $this->failNodeSave = TRUE;
    }
    else {
      $this->failBlockSaveAt = 2;
      $input->body[] = (object) ['type' => 'text', 'body' => '第二个组件'];
    }
    try {
      $this->drafts()->createDraft((new Uuid())->generate(), $input);
      self::fail('Expected injected storage failure');
    }
    catch (\RuntimeException $e) {
      self::assertStringStartsWith('Injected ', $e->getMessage());
    }
    self::assertSame(0, $this->countRows('draft_test_entity'));
    self::assertSame(0, $this->countRows('xinshi_page_draft_operation'));
  }

  public function testRoutesAndControllerPreserveAccessAndCacheContracts(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_api.routing.yml');
    self::assertSame('node.view',
      $routes['xinshi_api.v3.landingPage.canonical']['requirements']['_entity_access']);
    foreach (['capabilities' => 'GET', 'create' => 'POST', 'find' => 'GET', 'read' => 'GET', 'change' => 'POST'] as $name => $method) {
      $route = $routes['xinshi_api.v3.landingPage.drafts.' . $name];
      self::assertSame([$method], $route['methods']);
      self::assertSame('TRUE', $route['requirements']['_user_is_logged_in']);
      self::assertSame(['oauth2', 'cookie'], $route['options']['_auth']);
      self::assertTrue($route['options']['no_cache']);
    }
    self::assertSame('TRUE', $routes['xinshi_api.v3.landingPage.drafts.create']
      ['requirements']['_csrf_request_header_token']);
    self::assertSame('TRUE', $routes['xinshi_api.v3.landingPage.drafts.change']
      ['requirements']['_csrf_request_header_token']);
    $controller = new PageDraftController($this->drafts());
    $id = (new Uuid())->generate();
    $response = $controller->createDraft($id, new Request(content: json_encode($this->input())));
    self::assertSame(200, $response->getStatusCode());
    self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    self::assertTrue($response->headers->hasCacheControlDirective('private'));
    self::assertEquals(json_decode($response->getContent()),
      json_decode($controller->findDraft($id)->getContent()));
    $invalid = $controller->createDraft((new Uuid())->generate(), new Request(content: '{'));
    self::assertSame(422, $invalid->getStatusCode());
    self::assertSame('not_written', json_decode($invalid->getContent())->outcome);
  }

  public function testModelCannotPublishOrAssignOwnership(): void {
    foreach (['status' => TRUE, 'uid' => 1] as $key => $value) {
      $input = $this->input();
      $input->$key = $value;
      $this->refuses(fn() => $this->drafts()->createDraft((new Uuid())->generate(), $input), 'invalid_input');
    }
    self::assertSame(0, $this->countRows('draft_test_entity'));
    self::assertSame(0, $this->countRows('xinshi_page_draft_operation'));
  }

  private function newDraft(): array {
    $saved = $this->drafts()->createDraft((new Uuid())->generate(), $this->input());
    return $this->drafts()->readDraft($saved['result']['id']);
  }

  private function changeInput(array $page, string $action = 'append'): \stdClass {
    $input = (object) ['action' => $action, 'pageId' => $page['id'], 'expectedVersion' => $page['version']];
    if ($action === 'append') {
      $input->body = [(object) ['type' => 'btn', 'label' => '了解更多', 'href' => '/node/1', 'mode' => 'raised']];
    }
    return $input;
  }

  public function testAppendPreservesPageAndOriginalBlocksInBuilderPreview(): void {
    $page = $this->newDraft();
    $original = array_filter($this->created, fn($record) => $record['type'] === 'block_content');
    $input = $this->changeInput($page);
    $id = (new Uuid())->generate();
    $saved = $this->drafts()->changeDraft($id, $input);
    self::assertSame($page['id'], $saved['result']['id']);
    self::assertSame('draft', $saved['result']['status']);
    self::assertSame(1, $this->countRows('node'));
    self::assertSame(3, $this->countRows('draft_test_entity'));
    foreach ($original as $block_id => $block) {
      self::assertSame($block['values'], $this->created[$block_id]['values']);
    }
    $after = $this->drafts()->readDraft($page['id']);
    self::assertEquals([...$page['body'], ...$input->body], $after['body']);
    self::assertNotSame($page['version'], $after['version']);
    $controller = $this->getMockBuilder(PanelsIPEPageController::class)
      ->disableOriginalConstructor()->onlyMethods([])->getMock();
    $response = $controller->landingPageCanonical($this->created[$page['id']]['entity']);
    $preview = json_decode($response->getContent());
    self::assertTrue($preview->status);
    self::assertEquals($after['body'], array_map(fn($block) => $block->attributes->body, $preview->body));
    self::assertEquals($saved, $this->drafts()->changeDraft($id, $input));
    self::assertEquals($saved, $this->drafts()->findDraft($id));
    self::assertEquals($after, $this->drafts()->readDraft($page['id']));
  }

  public function testDifferentOperationsCannotAppendAgainstTheSameOldVersion(): void {
    $page = $this->newDraft();
    $input = $this->changeInput($page);
    $this->drafts()->changeDraft((new Uuid())->generate(), $input);
    $id = (new Uuid())->generate();
    $controller = new PageDraftController($this->drafts());
    $response = $controller->changeDraft($id, new Request(content: json_encode($input)));
    self::assertSame(409, $response->getStatusCode());
    self::assertSame('not_written', json_decode($response->getContent())->outcome);
    self::assertSame('version_conflict', json_decode($response->getContent())->code);
    self::assertNull($this->drafts()->findDraft($id));
    self::assertCount(2, $this->drafts()->readDraft($page['id'])['body']);
    self::assertSame(3, $this->countRows('draft_test_entity'));
  }

  public function testBlockEditWithoutNodeRevisionChangeInvalidatesApproval(): void {
    $page = $this->newDraft();
    $node = $this->created[$page['id']]['entity'];
    $vid = $node->getRevisionId();
    $blocks = array_filter($this->created, fn($record) => $record['type'] === 'block_content');
    $block = reset($blocks)['entity'];
    $block->set('body', ['format' => 'json', 'value' => '{"type":"text","body":"用户后续编辑"}']);
    $block->save();
    self::assertSame($vid, $node->getRevisionId());
    self::assertNotSame($page['version'], $this->drafts()->readDraft($page['id'])['version']);
    foreach (['append', 'delete'] as $action) {
      $this->refuses(fn() => $this->drafts()->changeDraft((new Uuid())->generate(),
        $this->changeInput($page, $action)), 'version_conflict');
    }
    self::assertSame(1, $this->countRows('xinshi_page_draft_operation'));
    self::assertSame(1, $this->countRows('node'));
  }

  public function testDeletedPageReceiptSurvivesMissingNodeRevocationAndDuplicatePost(): void {
    $page = $this->newDraft();
    $input = $this->changeInput($page, 'delete');
    $id = (new Uuid())->generate();
    $saved = $this->drafts()->changeDraft($id, $input);
    self::assertSame(['id' => $page['id'], 'status' => 'deleted'], $saved['result']);
    self::assertSame(0, $this->countRows('node'));
    $this->refuses(fn() => $this->drafts()->readDraft($page['id']), 'page_not_found');
    $this->entityGrants = ['view' => FALSE, 'update' => FALSE, 'delete' => FALSE];
    self::assertEquals($saved, $this->drafts()->findDraft($id));
    self::assertEquals($saved, $this->drafts()->changeDraft($id, $input));
    $this->refuses(fn() => $this->drafts()->changeDraft($id,
      $this->changeInput($page, 'append')), 'operation_conflict');
    $this->uid = '8';
    $this->refuses(fn() => $this->drafts()->findDraft($id), 'not_found');
    self::assertSame(2, $this->countRows('xinshi_page_draft_operation'));
  }

  public static function changeRefusalProvider(): array {
    return [
      ['append', 'owner', 'permission_denied'], ['delete', 'owner', 'permission_denied'],
      ['append', 'published', 'not_draft'], ['delete', 'published', 'not_draft'],
      ['append', 'permission', 'permission_denied'], ['delete', 'permission', 'permission_denied'],
      ['append', 'revision', 'version_conflict'], ['delete', 'revision', 'version_conflict'],
      ['append', 'translation', 'not_draft'], ['delete', 'translation', 'not_draft'],
    ];
  }

  #[DataProvider('changeRefusalProvider')]
  public function testChangeRevalidatesOwnershipStateAndPermission(string $action, string $change, string $reason): void {
    $page = $this->newDraft();
    if ($change === 'owner') {
      $this->uid = '8';
    }
    elseif ($change === 'permission') {
      $this->entityGrants[$action === 'append' ? 'update' : 'delete'] = FALSE;
    }
    elseif ($change === 'revision') {
      $this->latestRevision = 999;
    }
    elseif ($change === 'translation') {
      $this->created[$page['id']]['translations']['fr'] = $this->entity('node', [
        'type' => 'landing_page', 'title' => 'Published translation', 'langcode' => 'fr',
        'uid' => $this->uid, 'status' => TRUE,
      ]);
    }
    else {
      $node = $this->created[$page['id']]['entity'];
      $node->set('status', TRUE);
      $node->save();
    }
    $id = (new Uuid())->generate();
    $this->refuses(fn() => $this->drafts()->changeDraft($id, $this->changeInput($page, $action)), $reason);
    self::assertNull($this->drafts()->findDraft($id));
    self::assertSame(1, $this->countRows('node'));
    self::assertSame(2, $this->countRows('draft_test_entity'));
  }

  public function testAppendBlockPermissionRevocationDoesNotGrantNodeCreation(): void {
    $page = $this->newDraft();
    $this->grants['node'] = FALSE;
    self::assertContains('pages.update_draft', $this->drafts()->capabilities());
    self::assertNotContains('pages.create_draft', $this->drafts()->capabilities());
    $this->grants['block_content'] = FALSE;
    self::assertNotContains('pages.update_draft', $this->drafts()->capabilities());
    self::assertContains('pages.delete_draft', $this->drafts()->capabilities());
    $this->refuses(fn() => $this->drafts()->changeDraft((new Uuid())->generate(),
      $this->changeInput($page)), 'permission_denied');
    self::assertSame(1, $this->countRows('xinshi_page_draft_operation'));
  }

  public static function changeFailureProvider(): array {
    return [['append'], ['delete']];
  }

  #[DataProvider('changeFailureProvider')]
  public function testFailedChangeRollsBackPageBlocksAndReceipt(string $action): void {
    $page = $this->newDraft();
    $id = (new Uuid())->generate();
    $this->failNodeSave = $action === 'append';
    $this->failNodeDelete = $action === 'delete';
    try {
      $this->drafts()->changeDraft($id, $this->changeInput($page, $action));
      self::fail('Expected an injected change failure');
    }
    catch (\RuntimeException $e) {
      self::assertStringStartsWith('Injected ', $e->getMessage());
    }
    self::assertSame(2, $this->countRows('draft_test_entity'));
    self::assertSame(1, $this->countRows('node'));
    self::assertNull($this->drafts()->findDraft($id));
    self::assertEquals($page, $this->drafts()->readDraft($page['id']));
  }

  public function testChangeCannotPublishReassignOrSmuggleReplacementContent(): void {
    $page = $this->newDraft();
    foreach (['uid' => '1', 'status' => TRUE, 'title' => 'Replacement', 'executionId' => 'forged'] as $key => $value) {
      $input = $this->changeInput($page);
      $input->$key = $value;
      $this->refuses(fn() => $this->drafts()->changeDraft((new Uuid())->generate(), $input), 'invalid_input');
    }
    $delete = $this->changeInput($page, 'delete');
    $delete->body = [];
    $this->refuses(fn() => $this->drafts()->changeDraft((new Uuid())->generate(), $delete), 'invalid_input');
    self::assertSame(1, $this->countRows('xinshi_page_draft_operation'));
  }

}
