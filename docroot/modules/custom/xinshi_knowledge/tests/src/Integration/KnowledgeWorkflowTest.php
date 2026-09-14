<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_knowledge\Integration;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Form\FormState;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_knowledge\Knowledge;
use Drupal\xinshi_knowledge\Form\DocumentStatusForm;
use Drupal\xinshi_knowledge\Service\AttachmentExtraction;
use Drupal\xinshi_knowledge\Service\AttachmentParser;
use Drupal\xinshi_ai\Service\ProductDocuments;
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Real entities, file access, extraction queue and Search API database backend. */
final class KnowledgeWorkflowTest extends TestCase {

  protected function tearDown(): void {
    foreach (['denied_field', 'denied_node', 'denied_file'] as $key) {
      \Drupal::state()->delete('knowledge_test.' . $key);
    }
    $this->resetAccess();
  }

  public function testTextAttachmentIsSearchableAndReplacementsInvalidateResults(): void {
    Role::create(['id' => 'knowledge_reader', 'label' => 'Knowledge reader', 'permissions' => [
      'access content', 'view media', 'view xinshi knowledge',
    ]])->save();
    $reader = User::create(['name' => 'reader', 'status' => 1, 'roles' => ['knowledge_reader']]);
    $reader->save();
    \Drupal::currentUser()->setAccount($reader);
    $uri = 'private://xinshi-knowledge/specification.txt';
    mkdir('private://xinshi-knowledge');
    file_put_contents($uri, "Operating guide\nQuasarengine rated power 750 W\n知识库产品额定功率 750 瓦\n");
    $file = File::create(['uri' => $uri, 'filename' => 'specification.txt', 'uid' => 1, 'status' => 1]);
    $file->save();
    $media = Media::create(['bundle' => Knowledge::MEDIA, 'name' => 'Specification', 'uid' => 1,
      'status' => 1, Knowledge::FILE => ['target_id' => $file->id()]]);
    $media->save();
    $node = Node::create(['type' => Knowledge::NODE, 'title' => 'Equipment reference',
      'uid' => 1, 'status' => 1, 'langcode' => 'en', 'body' => ['value' => 'Read the attachment.'],
      Knowledge::ATTACHMENTS => [['target_id' => $media->id()]]]);
    $node->save();
    $documents = \Drupal::service('xinshi_knowledge.documents');
    $extraction = \Drupal::service('xinshi_knowledge.extraction');
    $state = $extraction->state((int) $media->id());
    $this->assertSame('pending', $state['status']);
    $this->assertDomain('processing', fn() => $documents->content($node, $reader));
    $queue = \Drupal::queue(Knowledge::QUEUE);
    $item = $queue->claimItem(120);
    $this->assertNotFalse($item);
    $this->assertSame($state['generation'], $item->data['generation']);
    \Drupal::service('plugin.manager.queue_worker')->createInstance(Knowledge::QUEUE)->processItem($item->data);
    $queue->deleteItem($item);
    $this->assertSame('ready', $extraction->state((int) $media->id())['status']);
    $index = Index::load(Knowledge::INDEX);
    $this->assertGreaterThan(0, $index->indexItems());
    $result = $documents->search('Quasarengine', 'en', [Knowledge::NODE], 0, $reader);
    $this->assertCount(1, $result['matches']);
    $source = $documents->content($node, $reader);
    $this->assertStringContainsString('750 W', $source['content']);
    $this->assertStringContainsString('File: specification.txt', $source['content']);
    $this->assertStringContainsString('Line: 2', $source['content']);
    $this->assertSame($source['snapshot'], $result['matches'][0]['snapshot']);
    $this->assertCount(1, $documents->search('额定功率', 'en', [Knowledge::NODE], 0, $reader)['matches']);
    $this->assertSame([], $documents->search('Quasarengine', 'zh-hans', [Knowledge::NODE], 0, $reader)['matches']);
    $anonymous = new AnonymousUserSession();
    \Drupal::currentUser()->setAccount($anonymous);
    $this->assertDomain('not_found', fn() => $documents->content($node, $anonymous));
    $this->assertFalse($media->access('view', $anonymous));
    $this->assertFalse($file->access('download', $anonymous));
    \Drupal::currentUser()->setAccount($reader);
    $uri2 = 'private://xinshi-knowledge/replacement.txt';
    file_put_contents($uri2, "Replacement novaengine power 900 W\n");
    $replacement = File::create(['uri' => $uri2, 'filename' => 'replacement.txt', 'uid' => 1, 'status' => 1]);
    $replacement->save();
    $media->setNewRevision(TRUE);
    $media->set(Knowledge::FILE, ['target_id' => $replacement->id()]);
    $media->save();
    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertDomain('processing', fn() => $documents->content($node, $reader));
    $this->assertSame([], $documents->search('Quasarengine', 'en', [Knowledge::NODE], 0, $reader)['matches']);
    // The earlier generation can never overwrite the replacement's state.
    $extraction->process(['mid' => (int) $media->id(), 'generation' => $state['generation']]);
    $updated = $extraction->state((int) $media->id());
    $this->assertSame('pending', $updated['status']);
    $extraction->process(['mid' => (int) $media->id(), 'generation' => $updated['generation']]);
    $this->assertNotSame($source['snapshot'], $documents->content($node, $reader)['snapshot']);
    $this->assertSame([], $documents->search('Quasarengine', 'en', [Knowledge::NODE], 0, $reader)['matches']);
    $index->indexItems();
    $this->assertCount(1, $documents->search('novaengine', 'en', [Knowledge::NODE], 0, $reader)['matches']);
    $node->setUnpublished()->save();
    $this->assertSame([], $documents->search('novaengine', 'en', [Knowledge::NODE], 0, $reader)['matches']);
    $node->setPublished()->save();
    $replacement->delete();
    $this->assertNull($extraction->state((int) $media->id()));
    $this->assertSame([], $documents->search('novaengine', 'en', [Knowledge::NODE], 0, $reader)['matches']);
  }

  public function testEntityFieldAndPrivateDownloadVetoesApplyToSearchAndRead(): void {
    [$node, $media, $file, $reader] = $this->fixture('permissions', 'Permissionneedle public facts');
    $this->extract($media);
    Index::load(Knowledge::INDEX)->indexItems();
    $documents = \Drupal::service('xinshi_knowledge.documents');
    $this->assertCount(1, $documents->search('Permissionneedle', 'en', [Knowledge::NODE], 0, $reader)['matches']);
    foreach ([['node', 'title'], ['node', 'body'], ['node', Knowledge::ATTACHMENTS],
      ['media', 'name'], ['media', Knowledge::FILE]] as $field) {
      \Drupal::state()->set('knowledge_test.denied_field', $field);
      $this->resetAccess();
      $this->assertDomain('not_found', fn() => $documents->content($node, $reader));
      $this->assertSame([], $documents->search('Permissionneedle', 'en', [Knowledge::NODE], 0, $reader)['matches']);
    }
    \Drupal::state()->delete('knowledge_test.denied_field');
    \Drupal::state()->set('knowledge_test.denied_node', (string) $node->id());
    $this->resetAccess();
    $this->assertFalse($media->access('view', $reader));
    $this->assertFalse($file->access('download', $reader));
    $this->assertDomain('not_found', fn() => $documents->content($node, $reader));
    \Drupal::state()->delete('knowledge_test.denied_node');
    \Drupal::state()->set('knowledge_test.denied_file', $file->getFileUri());
    $this->resetAccess();
    $this->assertDomain('not_found', fn() => $documents->content($node, $reader));
    $this->assertSame([], $documents->search('Permissionneedle', 'en', [Knowledge::NODE], 0, $reader)['matches']);
  }

  public function testMcpReadsBindChunksToAttachmentSnapshotAndContentRevision(): void {
    [$node, $media, $file, $reader] = $this->fixture('snapshots', str_repeat('资料😀', 7000));
    $this->extract($media);
    \Drupal::configFactory()->getEditable('xinshi_ai.settings')
      ->set('harness.mcp.product_documents', ['enabled' => TRUE, 'content_types' => [Knowledge::NODE]])->save();
    $service = new ProductDocuments(\Drupal::configFactory(), \Drupal::entityTypeManager(),
      \Drupal::service('entity_field.manager'), $reader, \Drupal::languageManager(),
      \Drupal::service('xinshi_knowledge.documents'));
    $args = ['id' => $node->uuid(), 'language' => 'en'];
    $first = $service->call('get_product_document', $args);
    $this->assertSame(16000, $first['nextOffset']);
    $this->assertSame(16000, mb_strlen($first['content']));
    $next = $args + ['offset' => $first['nextOffset'], 'revision' => $first['document']['revisionId'],
      'snapshot' => $first['document']['snapshot']];
    $second = $service->call('get_product_document', $next);
    $this->assertSame($first['document']['snapshot'], $second['document']['snapshot']);
    $this->assertNull($second['nextOffset']);
    $all = \Drupal::service('xinshi_knowledge.documents')->content($node, $reader)['content'];
    $this->assertSame($all, $first['content'] . $second['content']);
    $changed = $next;
    $changed['snapshot'] = str_repeat('0', 64);
    $this->assertDomain('changed', fn() => $service->call('get_product_document', $changed));
    $missing = $next;
    unset($missing['snapshot']);
    try {
      $service->call('get_product_document', $missing);
      $this->fail('Missing attachment snapshot must be rejected.');
    }
    catch (\InvalidArgumentException $error) {
      $this->assertSame('invalid_input', $error->getMessage());
    }
    // Even a file save in the same second and with the same size invalidates extraction.
    file_put_contents($file->getFileUri(), str_repeat('新文😀', 7000));
    $file->save();
    $this->assertDomain('processing', fn() => $service->call('get_product_document', $next));
    $this->extract($media);
    $this->assertDomain('changed', fn() => $service->call('get_product_document', $next));
  }

  public function testParserFailuresRetryThreeTimesAndEditorCanRequeue(): void {
    [$node, $media, , $reader] = $this->fixture('retry', 'Retryneedle facts');
    $parser = $this->createMock(AttachmentParser::class);
    $parser->expects($this->exactly(3))->method('parse')->willThrowException(new \RuntimeException('private parser detail'));
    $extraction = $this->extractionWith($parser);
    $state = $extraction->state((int) $media->id());
    $item = ['mid' => (int) $media->id(), 'generation' => $state['generation']];
    for ($attempt = 1; $attempt <= 3; $attempt++) {
      try {
        $extraction->process($item);
        $this->assertSame(3, $attempt);
      }
      catch (DelayedRequeueException) {
        $this->assertLessThan(3, $attempt);
      }
      $this->assertSame($attempt, (int) $extraction->state((int) $media->id())['attempts']);
    }
    $this->assertSame('failed', $extraction->state((int) $media->id())['status']);
    $this->assertSame('parser_unavailable', $extraction->state((int) $media->id())['error']);
    $this->assertDomain('parse_failed', fn() => \Drupal::service('xinshi_knowledge.documents')->content($node, $reader));
    $extraction->process($item);
    $role = Role::load('test_retry');
    $role->grantPermission('edit any ' . Knowledge::NODE . ' content')->save();
    $this->resetAccess();
    $form = DocumentStatusForm::create(\Drupal::getContainer());
    $this->assertTrue($form->access($node, $reader)->isAllowed());
    $formState = new FormState();
    $render = $form->buildForm([], $formState, $node);
    $this->assertStringNotContainsString('private parser detail', json_encode($render));
    $form->submitForm($render, $formState);
    $newState = $extraction->state((int) $media->id());
    $this->assertSame('pending', $newState['status']);
    $this->assertNotSame($state['generation'], $newState['generation']);
    $this->extract($media);
    $this->assertSame('ready', $extraction->state((int) $media->id())['status']);
  }

  public function testAWorkerCannotOverwriteAConcurrentRequeue(): void {
    [, $media, $file] = $this->fixture('concurrent', 'Concurrentneedle facts');
    $extraction = \Drupal::service('xinshi_knowledge.extraction');
    $original = $extraction->state((int) $media->id());
    $parser = $this->createMock(AttachmentParser::class);
    $parser->method('parse')->willReturnCallback(function () use ($extraction, $media, $file) {
      $extraction->schedule($media, TRUE);
      return ['hash' => hash_file('sha256', $file->getFileUri()),
        'segments' => [['location' => ['line' => 1], 'text' => 'stale reply']]];
    });
    $this->extractionWith($parser)->process(['mid' => (int) $media->id(), 'generation' => $original['generation']]);
    $current = $extraction->state((int) $media->id());
    $this->assertNotSame($original['generation'], $current['generation']);
    $this->assertSame('pending', $current['status']);
    $this->assertNull($current['segments']);
  }

  public static function obsoleteAttemptOutcomes(): array {
    return [[FALSE], [TRUE]];
  }

  #[DataProvider('obsoleteAttemptOutcomes')]
  public function testExpiredAttemptCannotOverwriteItsSuccessor(bool $fails): void {
    [, $media] = $this->fixture($fails ? 'expired_failure' : 'expired_success', 'Current attempt facts');
    $extraction = \Drupal::service('xinshi_knowledge.extraction');
    $state = $extraction->state((int) $media->id());
    $item = ['mid' => (int) $media->id(), 'generation' => $state['generation']];
    $parser = $this->createMock(AttachmentParser::class);
    $parser->method('parse')->willReturnCallback(function () use ($item, $extraction, $fails) {
      \Drupal::database()->update(Knowledge::TABLE)->condition('mid', $item['mid'])
        ->fields(['updated' => \Drupal::time()->getCurrentTime() - 121])->execute();
      $extraction->process($item);
      if ($fails) {
        throw new \RuntimeException('Obsolete attempt failed');
      }
      return ['hash' => str_repeat('0', 64),
        'segments' => [['location' => ['line' => 1], 'text' => 'Obsolete attempt facts']]];
    });
    $this->extractionWith($parser)->process($item);
    $current = $extraction->state((int) $media->id());
    $this->assertSame('ready', $current['status']);
    $this->assertSame(2, (int) $current['attempts']);
    $this->assertSame('', $current['error']);
    $this->assertStringContainsString('Current attempt facts', $current['segments']);
    $this->assertStringNotContainsString('Obsolete', $current['segments']);
  }

  public function testMissingPhysicalFileCannotReturnCachedText(): void {
    [$node, $media, $file, $reader] = $this->fixture('missing_file', 'Missingfileneedle facts');
    $this->extract($media);
    Index::load(Knowledge::INDEX)->indexItems();
    $documents = \Drupal::service('xinshi_knowledge.documents');
    $this->assertCount(1, $documents->search('Missingfileneedle', 'en', [Knowledge::NODE], 0, $reader)['matches']);
    unlink($file->getFileUri());
    $this->assertDomain('not_found', fn() => $documents->content($node, $reader));
    $this->assertSame([], $documents->search('Missingfileneedle', 'en', [Knowledge::NODE], 0, $reader)['matches']);
  }

  private function fixture(string $name, string $text): array {
    Role::create(['id' => 'test_' . $name, 'label' => $name, 'permissions' => [
      'access content', 'view media', 'view xinshi knowledge',
    ]])->save();
    $reader = User::create(['name' => 'reader_' . $name, 'status' => 1, 'roles' => ['test_' . $name]]);
    $reader->save();
    \Drupal::currentUser()->setAccount($reader);
    $uri = 'private://' . $name . '.txt';
    file_put_contents($uri, $text);
    $file = File::create(['uri' => $uri, 'filename' => $name . '.txt', 'uid' => 1, 'status' => 1]);
    $file->save();
    $media = Media::create(['bundle' => Knowledge::MEDIA, 'name' => $name, 'uid' => 1,
      'status' => 1, Knowledge::FILE => ['target_id' => $file->id()]]);
    $media->save();
    $node = Node::create(['type' => Knowledge::NODE, 'title' => $name, 'uid' => 1,
      'status' => 1, 'langcode' => 'en', 'body' => ['value' => 'Reference notes.'],
      Knowledge::ATTACHMENTS => [['target_id' => $media->id()]]]);
    $node->save();
    return [$node, $media, $file, $reader];
  }

  private function extract(Media $media): void {
    $extraction = \Drupal::service('xinshi_knowledge.extraction');
    $state = $extraction->state((int) $media->id());
    $extraction->process(['mid' => (int) $media->id(), 'generation' => $state['generation']]);
  }

  private function extractionWith(AttachmentParser $parser): AttachmentExtraction {
    return new AttachmentExtraction(\Drupal::database(), \Drupal::entityTypeManager(),
      \Drupal::service('queue'), $parser, \Drupal::time(), new NullLogger());
  }

  private function resetAccess(): void {
    foreach (['node', 'media', 'file'] as $type) {
      \Drupal::entityTypeManager()->getAccessControlHandler($type)->resetCache();
    }
  }

  private function assertDomain(string $code, callable $operation): void {
    try {
      $operation();
      $this->fail('Expected ' . $code);
    }
    catch (\DomainException $error) {
      $this->assertSame($code, $error->getMessage());
    }
  }

}
