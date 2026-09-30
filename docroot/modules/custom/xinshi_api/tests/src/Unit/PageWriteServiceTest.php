<?php

namespace Drupal\Tests\xinshi_api\Unit;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Component\Uuid\Php as Uuid;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityAccessControlHandlerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Layout\LayoutInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\filter\FilterFormatInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Field\LayoutSectionItemList;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorageInterface;
use Drupal\xinshi_api\Controller\PanelsIPEPageController;
use Drupal\xinshi_api\PageModerationPolicy;
use Drupal\xinshi_api\PageWriteService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationList;

/** Exercises actual controllers, sections and SQLite transactions with entity doubles. */
final class PageWriteServiceTest extends TestCase {

  private $database;
  private array $records = [];
  private array $revisions = [];
  private array $grants = [];
  private array $lookups = [];
  private int $nextId = 0;
  private int $saves = 0;
  private ?int $failSaveAt = NULL;
  private $entities;
  private $account;
  private PageWriteService $writer;
  private $usage;
  private ContainerBuilder $container;

  protected function setUp(): void {
    Database::addConnectionInfo('page_write_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'page_write_test');
    $this->database->schema()->createTable('node', ['fields' => ['nid' => ['type' => 'int', 'not null' => TRUE]], 'primary key' => ['nid']]);
    $this->database->schema()->createTable('test_record', ['fields' => [
      'id' => ['type' => 'int', 'not null' => TRUE], 'data' => ['type' => 'blob', 'size' => 'big'],
    ], 'primary key' => ['id']]);
    $this->entities = $this->createMock(EntityTypeManagerInterface::class);
    $storages = [];
    foreach (['node', 'block_content'] as $type) {
      $storage = $this->createMock($type === 'node' ? NodeStorageInterface::class : RevisionableStorageInterface::class);
      $storage->method('create')->willReturnCallback(fn($values) => $this->entity($type, $values));
      $storage->method('load')->willReturnCallback(fn($id) => $this->records[$id]['entity'] ?? NULL);
      $storage->method('loadRevision')->willReturnCallback(fn($vid) => $this->revisions[$type][$vid] ?? NULL);
      $storage->method('loadByProperties')->willReturnCallback(function ($properties) use ($type) {
        $this->lookups[] = $properties;
        return array_column(array_filter($this->records, fn($r) => $r['type'] === $type &&
          (!isset($properties['uuid']) || $r['values']['uuid'] === $properties['uuid'])), 'entity');
      });
      if ($type === 'node') {
        $storage->method('getLatestRevisionId')->willReturnCallback(fn($id) => $this->records[$id]['values']['latest'] ?? $this->records[$id]['values']['vid']);
      }
      $storages[$type] = $storage;
    }
    $access = $this->createMock(EntityAccessControlHandlerInterface::class);
    $access->method('createAccess')->willReturnCallback(fn($bundle) => $this->grants['create:' . $bundle] ?? TRUE);
    $this->entities->method('getAccessControlHandler')->willReturn($access);
    $handler = $this->createMock(\Drupal\content_translation\ContentTranslationHandlerInterface::class);
    $handler->method('getTranslationAccess')->willReturnCallback(fn() => \Drupal\Core\Access\AccessResult::allowedIf($this->grants['translate'] ?? TRUE));
    $this->entities->method('getHandler')->willReturn($handler);
    $format = $this->createMock(FilterFormatInterface::class);
    $format->method('access')->willReturnCallback(fn() => $this->grants['format'] ?? TRUE);
    $storages['filter_format'] = $this->createMock(EntityStorageInterface::class);
    $storages['filter_format']->method('load')->willReturn($format);
    $display = $this->createMock(LayoutBuilderEntityViewDisplay::class);
    $display->method('get')->with('third_party_settings')->willReturn(['layout_builder' => ['enabled' => TRUE]]);
    $storages['entity_view_display'] = $this->createMock(EntityStorageInterface::class);
    $storages['entity_view_display']->method('load')->willReturn($display);
    $this->entities->method('getStorage')->willReturnCallback(fn($type) => $storages[$type]);
    $this->account = $this->createMock(AccountInterface::class);
    $this->account->method('id')->willReturn('7');
    $this->account->method('isAuthenticated')->willReturnCallback(fn() => $this->grants['authenticated'] ?? TRUE);
    $this->account->method('hasPermission')->willReturnCallback(fn($permission) => $this->grants[$permission] ?? FALSE);
    $languages = $this->createMock(LanguageManagerInterface::class);
    $languages->method('getCurrentLanguage')->willReturn(new Language(['id' => 'en']));
    $languages->method('getLanguage')->willReturnCallback(fn($id) => new Language(['id' => $id]));
    $layouts = $this->createMock(LayoutPluginManagerInterface::class);
    $layouts->method('createInstance')->willReturnCallback(function ($id, $configuration) {
      $layout = $this->createMock(LayoutInterface::class);
      $layout->method('getConfiguration')->willReturn($configuration);
      return $layout;
    });
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(fn($s) => $s);
    $translation->method('translateString')->willReturnCallback(fn($s) => $s->getUntranslatedString());
    $this->container = new ContainerBuilder();
    foreach (['database' => $this->database, 'entity_type.manager' => $this->entities, 'current_user' => $this->account,
      'language_manager' => $languages, 'uuid' => new Uuid(), 'string_translation' => $translation,
      'plugin.manager.core.layout' => $layouts] as $name => $service) {
      $this->container->set($name, $service);
    }
    $modules = $this->createMock(\Drupal\Core\Extension\ModuleHandlerInterface::class);
    $modules->method('moduleExists')->willReturn(FALSE);
    $this->container->set('module_handler', $modules);
    $repository = $this->createMock(\Drupal\Core\Entity\EntityTypeRepositoryInterface::class);
    $repository->method('getEntityTypeFromClass')->willReturnCallback(fn($class) => $class === Node::class ? 'node' : 'block_content');
    $this->container->set('entity_type.repository', $repository);
    \Drupal::setContainer($this->container);
    $translations = $this->createMock(\Drupal\content_translation\ContentTranslationManagerInterface::class);
    $translations->method('isEnabled')->willReturn(TRUE);
    $this->usage = $this->createMock(\Drupal\layout_builder\InlineBlockUsageInterface::class);
    $this->usage->method('getUsage')->willReturnCallback(fn($id) => $this->grants['usage:' . $id] ?? FALSE);
    $this->usage->expects(self::never())->method('addUsage');
    $this->writer = new PageWriteService($this->database, $this->entities, $this->account, $languages, new Uuid(),
      new PageModerationPolicy(NULL, NULL, $this->account), $translations, $this->usage);
    $this->container->set('xinshi_api.page_writer', $this->writer);
  }

  protected function tearDown(): void {
    Database::removeConnection('page_write_test');
    \Drupal::unsetContainer();
  }

  private function entity(string $type, array $values) {
    if (is_array($values['langcode'] ?? NULL)) {
      $values['langcode'] = $values['langcode']['value'];
    }
    $id = ++$this->nextId;
    $values += ['title' => 'Original page', 'info' => 'Original block', 'langcode' => 'en', 'status' => TRUE,
      'reusable' => FALSE, 'uuid' => (new Uuid())->generate(), 'vid' => $id * 10, 'layout_builder__layout' => []];
    $entity = $this->createMock($type === 'node' ? Node::class : BlockContent::class);
    $this->records[$id] = ['entity' => $entity, 'type' => $type, 'values' => $values];
    $entity->method('id')->willReturn($id);
    $entity->method('uuid')->willReturn($values['uuid']);
    $entity->method('bundle')->willReturn($values['type']);
    $entity->method('getEntityTypeId')->willReturn($type);
    $entity->method('getCacheTags')->willReturn([]);
    $entity->method('language')->willReturn(new Language(['id' => $values['langcode']]));
    $entity->method('hasTranslation')->willReturnCallback(fn($lang) => $lang === $values['langcode'] || isset($this->records[$id]['translations'][$lang]));
    $entity->method('getTranslation')->willReturnCallback(fn($lang) => $this->records[$id]['translations'][$lang] ?? $entity);
    $entity->method('addTranslation')->willReturnCallback(fn($lang, $fields) => $this->translationOf($entity, $lang, $fields));
    $entity->method('toArray')->willReturnCallback(fn() => $this->records[$id]['values']);
    $definition = $this->createMock(\Drupal\Core\Field\FieldDefinitionInterface::class);
    $definition->method('isTranslatable')->willReturnCallback(fn() => $this->grants['translatable'] ?? TRUE);
    $entity->method('getFieldDefinition')->willReturn($definition);
    $entity->method('isDefaultTranslation')->willReturn(TRUE);
    $entity->method('isNew')->willReturnCallback(fn() => !isset($this->records[$id]['saved']));
    $entity->method('isPublished')->willReturnCallback(fn() => $this->records[$id]['values']['status']);
    $entity->method('label')->willReturnCallback(fn() => $this->records[$id]['values'][$type === 'node' ? 'title' : 'info']);
    $entity->method('getRevisionId')->willReturnCallback(fn() => $this->records[$id]['values']['vid']);
    $entity->method('access')->willReturnCallback(fn($op) => $this->grants[$id . ':' . $op] ?? TRUE);
    $entity->method('hasField')->willReturn(TRUE);
    $entity->method('validate')->willReturn(new ConstraintViolationList());
    $entity->method('get')->willReturnCallback(function ($name) use ($id) {
      $field = $this->createMock($name === 'layout_builder__layout' ? LayoutSectionItemList::class : FieldItemListInterface::class);
      $field->method('access')->willReturnCallback(fn() => $this->grants[$id . ':' . $name] ?? TRUE);
      $field->method('getValue')->willReturnCallback(fn() => $this->records[$id]['values'][$name] ?? []);
      $field->method('__get')->willReturnCallback(fn($key) => $this->records[$id]['values'][$name][$key] ?? NULL);
      $field->method('setValue')->willReturnCallback(function ($value) use ($id, $name) { $this->records[$id]['values'][$name] = $value; });
      $field->method('appendItem')->willReturnCallback(function ($value) use ($id, $name) { $this->records[$id]['values'][$name][] = $value; });
      if ($name === 'layout_builder__layout') {
        $field->method('getSections')->willReturnCallback(fn() => $this->records[$id]['values'][$name]);
      }
      return $field;
    });
    $entity->method('set')->willReturnCallback(function ($name, $value) use ($id, $entity) {
      $this->records[$id]['values'][$name] = $value;
      return $entity;
    });
    $entity->method('setNewRevision')->willReturnCallback(function () use ($id) { $this->records[$id]['newRevision'] = TRUE; });
    if ($type === 'block_content') {
      $entity->method('isReusable')->willReturnCallback(fn() => (bool) $this->records[$id]['values']['reusable']);
    }
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('/node/' . $id);
    $entity->method('toUrl')->willReturn($url);
    $entity->method('save')->willReturnCallback(function () use ($id, $type, $entity) {
      if ($this->records[$id]['newRevision'] ?? FALSE) {
        $this->records[$id]['values']['vid']++;
        $this->records[$id]['newRevision'] = FALSE;
      }
      $this->database->merge('test_record')->key('id', $id)->fields(['data' => serialize($this->records[$id]['values'])])->execute();
      if ($type === 'node') {
        $this->database->merge('node')->key('nid', $id)->fields(['nid' => $id])->execute();
      }
      $this->records[$id]['saved'] = TRUE;
      $this->revisions[$type][$this->records[$id]['values']['vid']] = $entity;
      if (++$this->saves === $this->failSaveAt) {
        throw new \RuntimeException('Injected save failure');
      }
      return 1;
    });
    return $entity;
  }

  private function translationOf($base, string $langcode, array $values) {
    $id = $base->id();
    $entity = $this->createMock($base instanceof Node ? Node::class : BlockContent::class);
    $this->records[$id]['values']['translations'][$langcode] = $values;
    $this->records[$id]['translations'][$langcode] = $entity;
    $entity->method('id')->willReturn($id);
    $entity->method('uuid')->willReturn($base->uuid());
    $entity->method('bundle')->willReturn($base->bundle());
    $entity->method('getEntityTypeId')->willReturn($base->getEntityTypeId());
    $entity->method('language')->willReturn(new Language(['id' => $langcode]));
    $entity->method('isDefaultTranslation')->willReturn(FALSE);
    $entity->method('isNew')->willReturn(FALSE);
    $entity->method('toUrl')->willReturn($base->toUrl());
    $entity->method('label')->willReturnCallback(fn() => $this->records[$id]['values']['translations'][$langcode]['title'] ?? $base->label());
    $entity->method('getRevisionId')->willReturnCallback(fn() => $base->getRevisionId());
    $entity->method('hasField')->willReturn(TRUE);
    $entity->method('access')->willReturn(TRUE);
    $entity->method('get')->willReturnCallback(function ($name) use ($id, $langcode) {
      $field = $this->createMock(FieldItemListInterface::class);
      $field->method('access')->willReturnCallback(fn() => $this->grants[$id . ':' . $name] ?? TRUE);
      return $field;
    });
    $entity->method('set')->willReturnCallback(function ($name, $value) use ($id, $langcode, $entity) {
      $this->records[$id]['values']['translations'][$langcode][$name] = $value;
      return $entity;
    });
    $entity->method('validate')->willReturn(new ConstraintViolationList());
    $entity->method('setNewRevision')->willReturnCallback(fn() => $base->setNewRevision(TRUE));
    $entity->method('save')->willReturnCallback(fn() => $base->save());
    return $entity;
  }

  private function fixture(): array {
    $block = $this->entity('block_content', ['type' => 'json', 'body' => ['value' => '{"type":"text","text":"before"}', 'format' => 'json']]);
    $block->save();
    $section = new Section('layout_onecol');
    $section->appendComponent(new SectionComponent((new Uuid())->generate(), 'content', [
      'id' => 'inline_block:json', 'block_revision_id' => $block->getRevisionId(),
    ]));
    $node = $this->entity('node', ['type' => 'landing_page', 'layout_builder__layout' => [$section]]);
    $node->save();
    return [$node, $block];
  }

  private function input($node, $block): array {
    return ['title' => 'Updated page', 'vid' => $node->getRevisionId(), 'body' => [
      ['uuid' => $block->uuid(), 'type' => 'json', 'attributes' => ['body' => ['type' => 'text', 'text' => 'after']]],
    ]];
  }

  private function persisted(): array {
    return $this->database->select('test_record', 'r')->fields('r')->orderBy('id')->execute()->fetchAllKeyed();
  }

  private function update($node, array $input) {
    $this->container->set('request_stack', new \Symfony\Component\HttpFoundation\RequestStack());
    $this->container->get('request_stack')->push(new Request(content: json_encode($input)));
    return (new PanelsIPEPageController($this->writer))->landingPageUpdate($node);
  }

  public function testUnauthorizedSharedUuidRejectedBeforeAnyWrites(): void {
    [$a, $own] = $this->fixture();
    [$b, $foreign] = $this->fixture();
    $this->records[$foreign->id()]['values']['reusable'] = TRUE;
    $foreign->save();
    $this->grants[$foreign->id() . ':update'] = FALSE;
    $before = $this->persisted();
    $input = $this->input($a, $own);
    $input['body'][] = $this->input($b, $foreign)['body'][0];
    $response = $this->update($a, $input);
    self::assertSame($before, $this->persisted(), 'Neither the title nor any block may be partially saved.');
    self::assertSame(403, $response->getStatusCode());
    self::assertTrue($this->records[$foreign->id()]['values']['reusable']);
    self::assertContains(['uuid' => $foreign->uuid()], $this->lookups);
  }

  public function testAuthorizedEditPreservesBlockIdentityAndSaveBehavior(): void {
    [$node, $block] = $this->fixture();
    $beforeVid = $block->getRevisionId();
    $nodeVid = $node->getRevisionId();
    self::assertSame(200, $this->update($node, $this->input($node, $block))->getStatusCode());
    self::assertCount(2, $this->records);
    self::assertSame($beforeVid, $block->getRevisionId());
    self::assertSame($nodeVid + 1, $node->getRevisionId());
    self::assertStringContainsString('after', $this->records[$block->id()]['values']['body']['value']);
    self::assertSame($block->getRevisionId(), $this->records[$node->id()]['values']['layout_builder__layout'][0]->getComponents()[array_key_first($this->records[$node->id()]['values']['layout_builder__layout'][0]->getComponents())]->get('configuration')['block_revision_id']);
  }

  public static function deniedGrants(): array {
    return [['node', 'update'], ['node', 'title'], ['node', 'layout_builder__layout'], ['block', 'update'], ['block', 'body'], ['block', 'reusable'], ['global', 'format']];
  }

  #[DataProvider('deniedGrants')]
  public function testDeniedAccessDoesNotWrite(string $subject, string $grant): void {
    [$node, $block] = $this->fixture();
    $this->grants[$subject === 'global' ? $grant : ($subject === 'node' ? $node->id() : $block->id()) . ':' . $grant] = FALSE;
    $before = $this->persisted();
    self::assertSame(403, $this->update($node, $this->input($node, $block))->getStatusCode());
    self::assertSame($before, $this->persisted());
  }

  public function testCreateRejectsPublishWithoutPermission(): void {
    $moderation = $this->createMock(\Drupal\content_moderation\ModerationInformationInterface::class);
    $moderation->method('isModeratedEntity')->willReturn(TRUE);
    $state = $this->createMock(\Drupal\workflows\StateInterface::class);
    $state->method('canTransitionTo')->willReturn(FALSE);
    $moderation->method('getOriginalState')->willReturn($state);
    $type = $this->createMock(\Drupal\workflows\WorkflowTypeInterface::class);
    $type->method('hasState')->willReturn(TRUE);
    $type->method('getState')->willReturn($state);
    $workflow = $this->createMock(\Drupal\workflows\WorkflowInterface::class);
    $workflow->method('getTypePlugin')->willReturn($type);
    $moderation->method('getWorkflowForEntity')->willReturn($workflow);
    $writer = new PageWriteService($this->database, $this->entities, $this->account,
      $this->container->get('language_manager'), new Uuid(), new PageModerationPolicy($moderation, NULL, $this->account), NULL, $this->createMock(\Drupal\layout_builder\InlineBlockUsageInterface::class));
    $this->container->set('request_stack', new \Symfony\Component\HttpFoundation\RequestStack());
    $this->container->get('request_stack')->push(new Request(content: json_encode([
      'title' => 'Unauthorized publication', 'body' => [['attributes' => ['body' => ['type' => 'text']]]],
    ])));
    $response = (new PanelsIPEPageController($writer))->landingPageBuilder();
    self::assertSame([], $this->persisted(), 'An unauthorized create must leave no published or partial page.');
    self::assertSame(403, $response->getStatusCode());
  }

  public function testCopyCreatesNewEntitiesWithoutChangingSource(): void {
    [$node, $block] = $this->fixture();
    $before = $this->persisted();
    $input = $this->input($node, $block);
    unset($input['body'][0]['uuid']);
    $copy = $this->writer->createPage($input);
    self::assertNotSame($node->id(), $copy->id());
    self::assertCount(4, $this->records);
    self::assertSame($before, array_intersect_key($this->persisted(), $before));
    self::assertSame([], $this->lookups);
    self::assertTrue($copy->isPublished());
  }

  public function testSaveFailureRollsBackTitleAndAllBlocks(): void {
    [$node, $block] = $this->fixture();
    $before = $this->persisted();
    $this->failSaveAt = $this->saves + 2;
    try {
      $this->writer->updatePage($node, $this->input($node, $block));
      self::fail('Expected injected failure.');
    }
    catch (\RuntimeException $error) {
      self::assertSame('Injected save failure', $error->getMessage());
    }
    self::assertSame($before, $this->persisted());
  }

  public function testPendingRevisionIsNotOverwritten(): void {
    [$node, $block] = $this->fixture();
    $this->records[$node->id()]['values']['latest'] = 999;
    $before = $this->persisted();
    self::assertSame(409, $this->update($node, $this->input($node, $block))->getStatusCode());
    self::assertSame($before, $this->persisted());
  }

  public function testAuthorizedSharedUuidKeepsUpdatingTheSameBlock(): void {
    [$node, $own] = $this->fixture();
    [$other, $shared] = $this->fixture();
    $beforeVid = $shared->getRevisionId();
    $shared->expects(self::once())->method('setAccessDependency')->with(self::callback(fn($dependency) => $dependency->id() === $node->id()));
    $shared->expects(self::never())->method('setNewRevision');
    self::assertSame(200, $this->update($node, $this->input($node, $shared))->getStatusCode());
    self::assertCount(4, $this->records);
    $configuration = $this->records[$other->id()]['values']['layout_builder__layout'][0]->getComponents();
    self::assertSame($shared->getRevisionId(), reset($configuration)->get('configuration')['block_revision_id']);
    self::assertSame($beforeVid, $shared->getRevisionId());
    self::assertStringContainsString('after', $this->records[$shared->id()]['values']['body']['value']);
  }

  public function testRecordedSharedDependencyIsNotReassigned(): void {
    [$node, $own] = $this->fixture();
    [$other, $shared] = $this->fixture();
    $this->grants['usage:' . $shared->id()] = (object) ['layout_entity_type' => 'node', 'layout_entity_id' => $other->id()];
    $shared->expects(self::never())->method('setAccessDependency');
    self::assertSame(200, $this->update($node, $this->input($node, $shared))->getStatusCode());
  }

  public function testCreateWithSharedUuidRetainsExistingReferenceBehavior(): void {
    [$source, $shared] = $this->fixture();
    $beforeVid = $shared->getRevisionId();
    $created = $this->writer->createPage($this->input($source, $shared));
    self::assertNotSame($source->id(), $created->id());
    self::assertCount(3, $this->records);
    self::assertSame($beforeVid, $shared->getRevisionId());
  }

  public function testUnknownUuidStillCreatesANewBlock(): void {
    [$node, $block] = $this->fixture();
    $input = $this->input($node, $block);
    $input['body'][0]['uuid'] = (new Uuid())->generate();
    self::assertSame(200, $this->update($node, $input)->getStatusCode());
    self::assertCount(3, $this->records);
  }

  public function testRepeatedUuidRemainsSupported(): void {
    [$node, $block] = $this->fixture();
    $input = $this->input($node, $block);
    $input['body'][] = $input['body'][0];
    self::assertSame(200, $this->update($node, $input)->getStatusCode());
    self::assertCount(2, $this->records);
    self::assertCount(2, $this->records[$node->id()]['values']['layout_builder__layout']);
  }

  public function testTranslationUsesSameBlockAndPreservesSourceFields(): void {
    [$node, $block] = $this->fixture();
    $sourceBody = $this->records[$block->id()]['values']['body'];
    $sourceTitle = $node->label();
    $translated = $this->writer->translatePage($node, 'en', 'fr', ['title' => 'French page']);
    self::assertSame($node->id(), $translated->id());
    self::assertCount(2, $this->records);
    self::assertSame($sourceBody, $this->records[$block->id()]['values']['body']);
    self::assertSame($sourceTitle, $node->label());
    self::assertSame($sourceBody['value'], $this->records[$block->id()]['values']['translations']['fr']['body']['value']);
    self::assertSame('French page', $this->records[$node->id()]['values']['translations']['fr']['title']);
    self::assertNull($this->records[$node->id()]['values']['translations']['fr']['path']['pid']);
  }

  public static function emptyTranslationRequests(): array {
    return [['{}'], ['']];
  }

  #[DataProvider('emptyTranslationRequests')]
  public function testTranslationControllerAcceptsEmptyInput(string $body): void {
    [$node, $block] = $this->fixture();
    $this->container->set('request_stack', new \Symfony\Component\HttpFoundation\RequestStack());
    $this->container->get('request_stack')->push(new Request(content: $body));
    $response = (new PanelsIPEPageController($this->writer))->landingPageTranslations(
      $node, new Language(['id' => 'en']), new Language(['id' => 'fr']),
    );
    self::assertSame(200, $response->getStatusCode());
    self::assertTrue(json_decode($response->getContent(), TRUE)['status']);
    self::assertSame($node->id(), json_decode($response->getContent(), TRUE)['data']['nid']);
  }

  public function testTranslationAccessRefusalDoesNotWrite(): void {
    [$node, $block] = $this->fixture();
    $this->grants['translate'] = FALSE;
    $before = $this->persisted();
    try {
      $this->writer->translatePage($node, 'en', 'fr', []);
      self::fail('Translation must be denied.');
    }
    catch (\Drupal\xinshi_api\PageDraftException $error) {
      self::assertSame('permission_denied', $error->reason);
    }
    self::assertSame($before, $this->persisted());
  }

  public function testNonTranslatableLayoutDoesNotOverwriteSource(): void {
    [$node, $block] = $this->fixture();
    $this->grants['translatable'] = FALSE;
    $before = $this->persisted();
    try {
      $this->writer->translatePage($node, 'en', 'fr', []);
      self::fail('Layout must be translatable.');
    }
    catch (\Drupal\xinshi_api\PageDraftException $error) {
      self::assertSame('translation_not_supported', $error->reason);
    }
    self::assertSame($before, $this->persisted());
  }

  public function testHistoricalContentCanBeRestoredUsingCurrentVid(): void {
    [$node, $block] = $this->fixture();
    $input = $this->input($node, $block);
    $input['revision_vid'] = 1;
    $input['body'][0]['attributes']['body']['text'] = 'restored history';
    $vid = $node->getRevisionId();
    self::assertSame(200, $this->update($node, $input)->getStatusCode());
    self::assertSame($vid + 1, $node->getRevisionId());
    self::assertStringContainsString('restored history', $this->records[$block->id()]['values']['body']['value']);
  }

  public function testCreatePermissionRefusalPreventsPartialUpdate(): void {
    [$node, $block] = $this->fixture();
    $input = $this->input($node, $block);
    $input['body'][] = ['attributes' => ['body' => ['type' => 'text']]];
    $this->grants['create:json'] = FALSE;
    $before = $this->persisted();
    self::assertSame(403, $this->update($node, $input)->getStatusCode());
    self::assertSame($before, $this->persisted());
  }

  public function testAnonymousCreateIsDenied(): void {
    $this->grants['authenticated'] = FALSE;
    try {
      $this->writer->createPage(['title' => 'Page', 'body' => [['attributes' => ['body' => []]]]]);
      self::fail('Anonymous creation must be denied.');
    }
    catch (\Drupal\xinshi_api\PageDraftException $error) {
      self::assertSame('permission_denied', $error->reason);
    }
    self::assertSame([], $this->persisted());
  }

  public function testControllerDoesNotExposeSaveException(): void {
    [$node, $block] = $this->fixture();
    $factory = $this->createMock(\Drupal\Core\Logger\LoggerChannelFactoryInterface::class);
    $channel = $this->createMock(\Drupal\Core\Logger\LoggerChannelInterface::class);
    $channel->expects(self::once())->method('error');
    $factory->method('get')->willReturn($channel);
    $this->container->set('logger.factory', $factory);
    $before = $this->persisted();
    $this->failSaveAt = $this->saves + 2;
    $response = $this->update($node, $this->input($node, $block));
    self::assertSame(500, $response->getStatusCode());
    self::assertSame('page_write_failed', json_decode($response->getContent(), TRUE)['code']);
    self::assertStringNotContainsString('Injected save failure', $response->getContent());
    self::assertSame($before, $this->persisted());
  }

  public function testMalformedBodyRejectedBeforeSideEffects(): void {
    [$node, $block] = $this->fixture();
    $input = $this->input($node, $block);
    $input['body'][0]['attributes']['body'] = FALSE;
    $before = $this->persisted();
    self::assertSame(422, $this->update($node, $input)->getStatusCode());
    self::assertSame($before, $this->persisted());
  }

}
