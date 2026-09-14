<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ParseMode\ParseModePluginManager;
use Drupal\xinshi_knowledge\Knowledge;

/** Search API returns candidates; only current, authorized source text leaves CMS. */
class DocumentRepository {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $fieldManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AttachmentExtraction $extraction,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ParseModePluginManager $parseModes,
  ) {}

  /** Keep the owned index scoped to knowledge plus explicitly selected bodies. */
  public function syncContentTypes(): void {
    $index = $this->index();
    if (!$index) {
      return;
    }
    $selected = $this->configFactory->get('xinshi_ai.settings')->get('harness.mcp.product_documents.content_types') ?? [];
    $types = [Knowledge::NODE];
    foreach (is_array($selected) ? $selected : [] as $type) {
      if (!is_string($type) || !preg_match('/^[a-z][a-z0-9_]{0,31}$/', $type)) {
        continue;
      }
      $fields = $this->fieldManager->getFieldDefinitions('node', $type);
      if (isset($fields['body']) && in_array($fields['body']->getType(), ['text', 'text_long', 'text_with_summary'], TRUE)) {
        $types[] = $type;
      }
    }
    $types = array_values(array_unique($types));
    sort($types);
    $datasources = $index->get('datasource_settings');
    $bundles = ['default' => FALSE, 'selected' => $types];
    if (($datasources['entity:node']['bundles'] ?? NULL) !== $bundles) {
      $datasources['entity:node']['bundles'] = $bundles;
      $index->set('datasource_settings', $datasources)->save();
    }
  }

  /** Each page is bounded even when old or inaccessible index rows are skipped. */
  public function search(string $needle, string $language, array $types, int $page,
    AccountInterface $account): array {
    $index = $this->index();
    if (!$index || !$index->status() || !$index->getServerInstance()?->status()) {
      throw new \DomainException('unavailable');
    }
    $query = $index->query([
      'search_api_access_account' => $account,
      'search_api_retrieved_field_values' => ['snapshot'],
      'skip result count' => TRUE,
    ])->keys($needle)->setLanguages([$language])
      ->setFulltextFields(['title', 'document_text'])
      ->setParseMode($this->parseModes->createInstance('terms'))
      ->addCondition('type', $types, 'IN')->addCondition('status', TRUE)
      ->sort('search_api_relevance', 'DESC')->sort('changed', 'DESC')->sort('search_api_id', 'ASC')
      // Search API DB retrieves stored values only for columns in its SELECT.
      // Sorting after the unique item ID selects the snapshot without reordering.
      ->sort('snapshot', 'ASC')
      ->range($page * 10, 11);
    $items = array_values($query->execute()->getResultItems());
    $matches = [];
    foreach (array_slice($items, 0, 10) as $item) {
      // Never let lazy field extraction substitute a live value for the indexed one.
      $indexed = $item->getField('snapshot', FALSE)?->getValues()[0] ?? NULL;
      if (!is_string($indexed) || !preg_match('/^entity:node\/(\d+):(.+)$/', $item->getId(), $id) || $id[2] !== $language) {
        continue;
      }
      $node = $this->entityTypeManager->getStorage('node')->load($id[1]);
      if (!$node instanceof NodeInterface || !in_array($node->bundle(), $types, TRUE) || !$node->hasTranslation($language)) {
        continue;
      }
      $node = $node->getTranslation($language);
      try {
        $source = $this->content($node, $account);
      }
      catch (\DomainException) {
        continue;
      }
      if (!hash_equals($indexed, $source['snapshot'])) {
        continue;
      }
      $position = mb_stripos($source['content'], $needle);
      $matches[] = ['node' => $node, 'snapshot' => $source['snapshot'],
        'excerpt' => mb_substr($source['content'], max(0, ($position === FALSE ? 0 : $position) - 60), 300)];
    }
    return ['matches' => $matches, 'nextPage' => count($items) > 10 && $page < 1000 ? $page + 1 : NULL];
  }

  /**
   * A null account is reserved for the internal indexer. MCP always supplies one.
   *
   * @return array{content: string, snapshot: string}
   */
  public function content(NodeInterface $node, ?AccountInterface $account = NULL): array {
    if (!$node->isDefaultRevision() || !$node->isPublished() || !$node->hasField('body') ||
        ($account && (!$node->access('view', $account) || !$node->get('body')->access('view', $account) ||
          !$node->get('title')->access('view', $account)))) {
      throw new \DomainException('not_found');
    }
    $content = self::bodyText((string) $node->get('body')->value);
    $versions = [(string) $node->getRevisionId(), $node->language()->getId(),
      $node->label(), hash('sha256', $content)];
    if ($node->bundle() === Knowledge::NODE) {
      if (!$node->hasField(Knowledge::ATTACHMENTS) || ($account &&
          (!$account->isAuthenticated() || !$account->hasPermission('view xinshi knowledge') ||
          !$node->get(Knowledge::ATTACHMENTS)->access('view', $account)))) {
        throw new \DomainException('not_found');
      }
      $english = str_starts_with($node->language()->getId(), 'en');
      foreach ($node->get(Knowledge::ATTACHMENTS) as $reference) {
        $media = $reference->entity;
        $file = $media instanceof MediaInterface ? $this->extraction->file($media) : NULL;
        if (!$media instanceof MediaInterface || !$media->isDefaultRevision() || !$media->isPublished() ||
            !$file || !$file->isPermanent() || !str_starts_with($file->getFileUri(), 'private://') ||
            !is_file($file->getFileUri()) || !is_readable($file->getFileUri())) {
          throw new \DomainException('not_found');
        }
        if ($account) {
          if (!$media->access('view', $account) || !$media->get('name')->access('view', $account) ||
              !$media->get(Knowledge::FILE)->access('view', $account) || !$file->access('download', $account)) {
            throw new \DomainException('not_found');
          }
          // Honor other modules' private-download vetoes, too. MCP uses current_user.
          $headers = $this->moduleHandler->invokeAll('file_download', [$file->getFileUri()]);
          if (!$headers || in_array(-1, $headers, TRUE)) {
            throw new \DomainException('not_found');
          }
        }
        $state = $this->extraction->state((int) $media->id());
        if (!$state || $state['source_key'] !== $this->extraction->sourceKey($media, $file) ||
            in_array($state['status'], ['pending', 'processing'], TRUE)) {
          throw new \DomainException('processing');
        }
        if ($state['status'] !== 'ready') {
          throw new \DomainException('parse_failed');
        }
        $versions[] = [$state['source_key'], $state['generation'], $state['file_hash']];
        $content .= "\n\n[" . ($english ? 'File: ' : '文件：') . $file->getFilename() . "]\n";
        $segments = json_decode($state['segments'], TRUE, 32, JSON_THROW_ON_ERROR);
        $labels = $english
          ? ['page' => 'Page', 'sheet' => 'Sheet', 'tableRow' => 'Extracted table row', 'section' => 'Section', 'paragraph' => 'Paragraph', 'line' => 'Line']
          : ['page' => '页', 'sheet' => '工作表', 'tableRow' => '解析表格行', 'section' => '章节', 'paragraph' => '段落', 'line' => '行'];
        foreach ($segments as $segment) {
          $location = [];
          foreach ($segment['location'] as $key => $value) {
            if (isset($labels[$key])) {
              $location[] = $labels[$key] . ': ' . $value;
            }
          }
          $content .= '[' . implode(' / ', $location) . "]\n" . $segment['text'] . "\n";
        }
      }
    }
    if (mb_strlen($content) > 10000000) {
      throw new \DomainException('parse_failed');
    }
    return ['content' => trim($content), 'snapshot' => hash('sha256', json_encode($versions, JSON_THROW_ON_ERROR))];
  }

  public static function bodyText(string $html): string {
    $html = preg_replace('@<(script|style)\b[^>]*>.*?</\1>@is', '', $html);
    $html = preg_replace('@<br\b[^>]*>|</(?:p|div|li|h[1-6])>@i', "\n", $html);
    return trim(Html::decodeEntities(strip_tags($html)));
  }

  private function index(): ?IndexInterface {
    $index = $this->entityTypeManager->getStorage('search_api_index')->load(Knowledge::INDEX);
    return $index instanceof IndexInterface ? $index : NULL;
  }

}
