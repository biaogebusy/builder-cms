<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueFactory;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\search_api\IndexInterface;
use Drupal\xinshi_knowledge\Knowledge;
use Psr\Log\LoggerInterface;

/** Durable extraction state with generation-checked writes and bounded retries. */
class AttachmentExtraction {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QueueFactory $queue,
    private readonly AttachmentParser $parser,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  public function state(int $mid): ?array {
    return $this->database->select(Knowledge::TABLE, 'e')->fields('e')
      ->condition('mid', $mid)->execute()->fetchAssoc() ?: NULL;
  }

  public function file(MediaInterface $media): ?FileInterface {
    if ($media->bundle() !== Knowledge::MEDIA || !$media->hasField(Knowledge::FILE)) {
      return NULL;
    }
    $file = $media->get(Knowledge::FILE)->entity;
    return $file instanceof FileInterface ? $file : NULL;
  }

  public function sourceKey(MediaInterface $media, FileInterface $file): string {
    return hash('sha256', json_encode([
      'parser' => 1, 'media' => $media->uuid(), 'revision' => (string) $media->getRevisionId(),
      'file' => $file->uuid(), 'uri' => $file->getFileUri(),
      'name' => $file->getFilename(), 'size' => $file->getSize(), 'changed' => $file->getChangedTime(),
    ], JSON_THROW_ON_ERROR));
  }

  /** Schedule only default revisions; replacing a source erases its old text. */
  public function schedule(MediaInterface $media, bool $force = FALSE): void {
    if ($media->bundle() !== Knowledge::MEDIA || !$media->isDefaultRevision()) {
      return;
    }
    $file = $this->file($media);
    if (!$file) {
      $this->forget((int) $media->id());
      return;
    }
    $key = $this->sourceKey($media, $file);
    $previous = $this->state((int) $media->id());
    if (!$force && ($previous['source_key'] ?? NULL) === $key) {
      return;
    }
    $generation = bin2hex(random_bytes(32));
    // Queue creation and invalidation commit together with the entity save.
    $transaction = $this->database->startTransaction();
    $this->database->merge(Knowledge::TABLE)->key('mid', (int) $media->id())->fields([
      'source_key' => $key, 'generation' => $generation, 'fid' => (int) $file->id(),
      'status' => 'pending', 'attempts' => 0, 'error' => '', 'file_hash' => '',
      'segments' => NULL, 'updated' => $this->time->getCurrentTime(),
    ])->execute();
    $this->queue->get(Knowledge::QUEUE)->createItem(['mid' => (int) $media->id(), 'generation' => $generation]);
    unset($transaction);
    $this->trackReferences((int) $media->id());
  }

  public function process(array $item): void {
    $mid = $item['mid'] ?? 0;
    $generation = $item['generation'] ?? '';
    $state = $this->state((int) $mid);
    if (!$state || $state['generation'] !== $generation || in_array($state['status'], ['ready', 'failed'], TRUE)) {
      return;
    }
    $media = $this->loadMedia((int) $mid);
    $file = $media ? $this->file($media) : NULL;
    if (!$media || !$file) {
      $this->forget((int) $mid);
      return;
    }
    if ($state['source_key'] !== $this->sourceKey($media, $file)) {
      $this->schedule($media);
      return;
    }
    $now = $this->time->getCurrentTime();
    $claim = $this->database->update(Knowledge::TABLE)
      ->condition('mid', $mid)->condition('generation', $generation)
      ->condition('attempts', (int) $state['attempts'])
      ->condition($this->database->condition('OR')->condition('status', 'pending')
        ->condition($this->database->condition('AND')->condition('status', 'processing')->condition('updated', $now - 120, '<')))
      ->fields(['status' => 'processing', 'updated' => $now])->expression('attempts', 'attempts + 1')->execute();
    if (!$claim) {
      throw new DelayedRequeueException(60);
    }
    $attempt = (int) $state['attempts'] + 1;
    try {
      $parsed = $this->parser->parse($file);
    }
    catch (\Throwable $error) {
      $code = $error instanceof \DomainException ? $error->getMessage() : 'parser_unavailable';
      $retry = $code === 'parser_unavailable' && $attempt < 3;
      $changed = $this->database->update(Knowledge::TABLE)
        ->condition('mid', $mid)->condition('generation', $generation)->condition('attempts', $attempt)
        ->fields(['status' => $retry ? 'pending' : 'failed', 'error' => $code,
          'segments' => NULL, 'updated' => $this->time->getCurrentTime()])->execute();
      if ($changed && $retry) {
        throw new DelayedRequeueException(60);
      }
      if ($changed) {
        $this->logger->warning('Knowledge attachment @mid could not be extracted (@reason).',
          ['@mid' => $mid, '@reason' => $code]);
        $this->trackReferences((int) $mid);
      }
      return;
    }
    // An editor may have replaced or deleted the file while Tika was working.
    $current = $this->loadMedia((int) $mid);
    $currentFile = $current ? $this->file($current) : NULL;
    if (!$current || !$currentFile) {
      $this->forget((int) $mid);
      return;
    }
    if ($state['source_key'] !== $this->sourceKey($current, $currentFile)) {
      $this->schedule($current);
      return;
    }
    $changed = $this->database->update(Knowledge::TABLE)
      ->condition('mid', $mid)->condition('generation', $generation)->condition('attempts', $attempt)
      ->fields(['status' => 'ready', 'error' => '', 'file_hash' => $parsed['hash'],
        'segments' => json_encode($parsed['segments'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'updated' => $this->time->getCurrentTime()])->execute();
    if ($changed) {
      $this->trackReferences((int) $mid);
    }
  }

  public function fileChanged(FileInterface $file, bool $deleted = FALSE): void {
    $ids = $this->entityTypeManager->getStorage('media')->getQuery()->accessCheck(FALSE)
      ->condition('bundle', Knowledge::MEDIA)->condition(Knowledge::FILE . '.target_id', $file->id())->execute();
    foreach ($ids as $mid) {
      if ($deleted) {
        $this->forget((int) $mid);
      }
      elseif ($media = $this->loadMedia((int) $mid)) {
        // A replacement can have the same size and second-resolution timestamp.
        $this->schedule($media, TRUE);
      }
    }
  }

  public function forget(int $mid): void {
    $this->database->delete(Knowledge::TABLE)->condition('mid', $mid)->execute();
    $this->trackReferences($mid);
  }

  /** Mark default node translations dirty; Search API cron does the indexing. */
  public function trackReferences(int $mid): void {
    $index = $this->entityTypeManager->getStorage('search_api_index')->load(Knowledge::INDEX);
    if (!$index instanceof IndexInterface || !$index->status()) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', Knowledge::NODE)
      ->condition(Knowledge::ATTACHMENTS . '.target_id', $mid)->execute();
    $items = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      foreach ($node->getTranslationLanguages() as $language) {
        $items[] = $node->id() . ':' . $language->getId();
      }
    }
    if ($items) {
      $index->trackItemsUpdated('entity:node', $items);
    }
  }

  private function loadMedia(int $mid): ?MediaInterface {
    $storage = $this->entityTypeManager->getStorage('media');
    $storage->resetCache([$mid]);
    $this->entityTypeManager->getStorage('file')->resetCache();
    $media = $storage->load($mid);
    return $media instanceof MediaInterface ? $media : NULL;
  }

}
