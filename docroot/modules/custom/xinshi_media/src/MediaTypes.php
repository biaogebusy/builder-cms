<?php

declare(strict_types=1);

namespace Drupal\xinshi_media;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/** Discovers explicitly included source fields without importing business document stores. */
final class MediaTypes {

  public const EXTENSIONS = [
    'image' => ['png', 'jpg', 'jpeg', 'gif', 'webp'],
    'vector' => ['svg'],
    'document' => ['pdf', 'docx', 'xlsx', 'pptx', 'txt', 'csv', 'md'],
    'audio' => ['mp3', 'm4a', 'wav', 'ogg', 'aac'],
    'video' => ['mp4', 'webm'],
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EntityFieldManagerInterface $fields,
    private readonly ConfigFactoryInterface $config,
  ) {}

  /** Returns supported configured bundles; discovery never changes site configuration. */
  public function all(): array {
    $result = [];
    $settings = $this->config->get('xinshi_media.settings');
    foreach ($settings->get('bundles') ?? [] as $bundle) {
      $type = $this->entities->getStorage('media_type')->load($bundle);
      if (!$type) continue;
      $source = $type->getSource();
      $field = $source->getSourceFieldDefinition($type);
      $kind = match ($source->getPluginId()) {
        'image' => 'image', 'audio_file' => 'audio', 'video_file' => 'video',
        'file' => $bundle === 'vector' ? 'vector' : 'document', default => NULL,
      };
      if (!$field || !$kind || !in_array($field->getType(), ['image', 'file'], TRUE)) continue;
      $extensions = array_values(array_intersect(self::EXTENSIONS[$kind],
        preg_split('/\s+/', strtolower(trim((string) $field->getSetting('file_extensions'))))));
      $fieldLimit = \Drupal\Component\Utility\Bytes::toNumber($field->getSetting('max_filesize') ?: '0');
      $maximum = (int) ($settings->get('max_upload_bytes') ?: 104857600);
      if ($fieldLimit > 0) $maximum = min($maximum, $fieldLimit);
      if ($kind === 'vector') $maximum = min($maximum, 1048576);
      if ($kind === 'image') $maximum = min($maximum, 20971520);
      $result[$bundle] = ['bundle' => $bundle, 'kind' => $kind, 'label' => $type->label(),
        'field' => $field->getName(), 'extensions' => $extensions, 'maxBytes' => (int) $maximum,
        'scheme' => $field->getFieldStorageDefinition()->getSetting('uri_scheme') ?: 'public'];
    }
    return $result;
  }

  /** Resolves only configured, supported source fields. */
  public function get(string $bundle): array {
    return $this->all()[$bundle] ?? throw new MediaException('unsupported_media_type', 415);
  }

  /** Exposes field and create access as capabilities, not as a grant for later writes. */
  public function capabilities(AccountInterface $account): array {
    $result = [];
    $access = $this->entities->getAccessControlHandler('media');
    foreach ($this->all() as $type) {
      $media = $this->entities->getStorage('media')->create(['bundle' => $type['bundle'], 'uid' => $account->id()]);
      $type['canUpload'] = (bool) $type['extensions'] && $access->createAccess($type['bundle'], $account) &&
        $media->get($type['field'])->access('edit', $account) && $media->get('name')->access('edit', $account);
      $result[] = $type;
    }
    return $result;
  }

}
