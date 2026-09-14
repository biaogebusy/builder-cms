<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_knowledge\Service\DocumentRepository;

/** Reads selected published node bodies, with entity and field access checks. */
class ProductDocuments {

  public const CHUNK_LENGTH = 16000;
  private const PAGE_SIZE = 10;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $fieldManager,
    private readonly AccountInterface $account,
    private readonly LanguageManagerInterface $languageManager,
    private readonly ?DocumentRepository $documentIndex = NULL,
  ) {}

  /** Only node bundles with a normal text body can supply product documents. */
  public function availableContentTypes(): array {
    $result = [];
    foreach ($this->entityTypeManager->getStorage('node_type')->loadMultiple() as $type) {
      $fields = $this->fieldManager->getFieldDefinitions('node', $type->id());
      if (isset($fields['body']) && in_array($fields['body']->getType(), ['text', 'text_long', 'text_with_summary'], TRUE)) {
        $result[$type->id()] = $type->label();
      }
    }
    asort($result);
    return $result;
  }

  public function toolNames(): array {
    $settings = $this->settings();
    if (!$this->account->isAuthenticated() || !$settings['mcp']['product_documents']['enabled'] || !$this->contentTypes()) {
      return [];
    }
    return array_values(array_diff(['search_product_documents', 'get_product_document'], $settings['tools']['disabled']));
  }

  public function call(string $name, array $arguments): array {
    if (!in_array($name, $this->toolNames(), TRUE)) {
      throw new \DomainException('disabled');
    }
    $search = $name === 'search_product_documents';
    $allowed = $search ? ['query', 'page', 'language'] : ['id', 'offset', 'revision', 'snapshot', 'language'];
    if (array_diff(array_keys($arguments), $allowed)) {
      throw new \InvalidArgumentException('invalid_input');
    }
    $language = $arguments['language'] ?? NULL;
    if (!is_string($language) || !$this->languageManager->getLanguage($language)) {
      throw new \InvalidArgumentException('invalid_input');
    }
    return $search ? $this->search($arguments, $language) : $this->read($arguments, $language);
  }

  private function settings(): array {
    return HarnessSettings::normalize($this->configFactory->get('xinshi_ai.settings')->get('harness'));
  }

  private function contentTypes(): array {
    return array_values(array_intersect($this->settings()['mcp']['product_documents']['content_types'],
      array_keys($this->availableContentTypes())));
  }

  private function search(array $arguments, string $language): array {
    $needle = $arguments['query'] ?? NULL;
    $page = $arguments['page'] ?? 0;
    if (!is_string($needle) || trim($needle) === '' || mb_strlen(trim($needle)) > 200 ||
        !is_int($page) || $page < 0 || $page > 1000) {
      throw new \InvalidArgumentException('invalid_input');
    }
    if ($this->documentIndex) {
      $found = $this->documentIndex->search(trim($needle), $language, $this->contentTypes(), $page, $this->account);
      return ['documents' => array_map(fn(array $match): array => $this->metadata($match['node']) +
        ['snapshot' => $match['snapshot'], 'excerpt' => $match['excerpt']], $found['matches']),
        'nextPage' => $found['nextPage']];
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck(TRUE)
      ->condition('type', $this->contentTypes(), 'IN')
      ->condition('status', 1, '=', $language)
      ->condition('langcode', $language);
    $query->condition($query->orConditionGroup()
      ->condition('title', trim($needle), 'CONTAINS', $language)
      ->condition('body.value', trim($needle), 'CONTAINS', $language));
    $ids = array_values($query->sort('changed', 'DESC', $language)->sort('nid', 'DESC')
      ->range($page * self::PAGE_SIZE, self::PAGE_SIZE + 1)->execute());
    $documents = [];
    foreach ($storage->loadMultiple(array_slice($ids, 0, self::PAGE_SIZE)) as $node) {
      $translation = $this->accessibleTranslation($node, $language);
      if ($translation) {
        $documents[] = $this->metadata($translation) + ['excerpt' => mb_substr($this->body($translation), 0, 300)];
      }
    }
    return ['documents' => $documents,
      'nextPage' => count($ids) > self::PAGE_SIZE && $page < 1000 ? $page + 1 : NULL];
  }

  private function read(array $arguments, string $language): array {
    $id = $arguments['id'] ?? NULL;
    $offset = $arguments['offset'] ?? 0;
    $revision = $arguments['revision'] ?? NULL;
    $snapshot = $arguments['snapshot'] ?? NULL;
    if (!is_string($id) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) ||
        !is_int($offset) || $offset < 0 || $offset > 10000000 ||
        ($revision !== NULL && (!is_string($revision) || !preg_match('/^\d{1,32}$/', $revision))) ||
        ($snapshot !== NULL && (!is_string($snapshot) || !preg_match('/^[a-f0-9]{64}$/', $snapshot))) ||
        ($offset > 0 && ($revision === NULL || ($this->documentIndex && $snapshot === NULL)))) {
      throw new \InvalidArgumentException('invalid_input');
    }
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'uuid' => $id, 'type' => $this->contentTypes(),
    ]);
    $node = $nodes ? reset($nodes) : NULL;
    $translation = $node instanceof NodeInterface ? $this->accessibleTranslation($node, $language) : NULL;
    if (!$translation) {
      throw new \DomainException('not_found');
    }
    if ($revision !== NULL && $revision !== (string) $translation->getRevisionId()) {
      throw new \DomainException('changed');
    }
    $source = $this->documentIndex?->content($translation, $this->account);
    if ($snapshot !== NULL && (!$source || !hash_equals($source['snapshot'], $snapshot))) {
      throw new \DomainException('changed');
    }
    $body = $source['content'] ?? $this->body($translation);
    $length = mb_strlen($body);
    if ($offset > $length) {
      throw new \InvalidArgumentException('invalid_input');
    }
    return [
      'document' => $this->metadata($translation) + ($source ? ['snapshot' => $source['snapshot']] : []),
      'content' => mb_substr($body, $offset, self::CHUNK_LENGTH),
      'offset' => $offset, 'totalCharacters' => $length,
      'nextOffset' => $offset + self::CHUNK_LENGTH < $length ? $offset + self::CHUNK_LENGTH : NULL,
    ];
  }

  private function accessibleTranslation(NodeInterface $node, string $language): ?NodeInterface {
    if (!in_array($node->bundle(), $this->contentTypes(), TRUE) || !$node->hasTranslation($language)) {
      return NULL;
    }
    $translation = $node->getTranslation($language);
    if (!$translation->isPublished() || !$translation->access('view', $this->account) ||
        !$translation->hasField('body') || !$translation->get('body')->access('view', $this->account) ||
        !$translation->get('title')->access('view', $this->account)) {
      return NULL;
    }
    return $translation;
  }

  private function metadata(NodeInterface $node): array {
    return [
      'id' => $node->uuid(), 'title' => $node->label(),
      'url' => $node->toUrl('canonical', ['absolute' => FALSE, 'language' => $node->language()])->toString(),
      'langcode' => $node->language()->getId(), 'changed' => (int) $node->getChangedTime(),
      'revisionId' => (string) $node->getRevisionId(),
    ];
  }

  private function body(NodeInterface $node): string {
    $html = (string) $node->get('body')->value;
    $html = preg_replace('@<(script|style)\b[^>]*>.*?</\1>@is', '', $html);
    $html = preg_replace('@<br\b[^>]*>|</(?:p|div|li|h[1-6])>@i', "\n", $html);
    return trim(Html::decodeEntities(strip_tags($html)));
  }

}
