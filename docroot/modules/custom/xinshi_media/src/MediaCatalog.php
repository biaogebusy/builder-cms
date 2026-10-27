<?php

declare(strict_types=1);

namespace Drupal\xinshi_media;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;

/** Provides bounded, author-scoped discovery and reauthorizes every individual operation. */
final class MediaCatalog {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EntityRepositoryInterface $repository,
    private readonly AccountInterface $account,
    private readonly MediaTypes $types,
    private readonly MediaPolicy $policy,
    private readonly FileUrlGeneratorInterface $urls,
    private readonly Connection $database,
    private readonly ModuleHandlerInterface $modules,
  ) {}

  public function capabilities(): array {
    return ['types' => $this->types->capabilities($this->account),
      'canViewAll' => $this->policy->all($this->account)];
  }

  /** A cursor advances even across inaccessible rows; no hidden-record totals are returned. */
  public function listing(array $input): array {
    $cursor = $input['cursor'] ?? '';
    $name = $input['name'] ?? '';
    $kind = $input['kind'] ?? '';
    $purpose = $input['purpose'] ?? '';
    $scope = $input['scope'] ?? ($this->policy->all($this->account) ? 'all' : 'mine');
    if (!is_string($cursor) || ($cursor !== '' && !ctype_digit($cursor)) || strlen($cursor) > 18 ||
      !is_string($name) || mb_strlen($name) > 128 || !is_string($kind) ||
      !in_array($purpose, ['', 'web-image', 'image-media', 'raster-image'], TRUE) ||
      !in_array($scope, ['mine', 'all'], TRUE)) throw new MediaException('invalid_filters');
    $name = trim($name);
    if ($scope === 'all' && !$this->policy->all($this->account)) throw new MediaException('permission_denied', 403);
    $bundles = array_keys(array_filter($this->types->all(), static fn($type) => !$kind || $type['kind'] === $kind));
    if (!$bundles) return ['rows' => [], 'nextCursor' => NULL, 'scope' => $scope];
    // Entity access hooks do not necessarily affect SQL. Always add the ownership condition here.
    $query = $this->entities->getStorage('media')->getQuery()->accessCheck(TRUE)
      ->condition('bundle', $bundles, 'IN')->sort('mid', 'DESC')->range(0, 241);
    if ($scope === 'mine') $query->condition('uid', $this->account->id());
    if ($cursor !== '') $query->condition('mid', (int) $cursor, '<');
    $hidden = $this->database->select('xinshi_media_hidden', 'hidden')->fields('hidden', ['mid']);
    $query->condition('mid', $hidden, 'NOT IN');
    // Apply name predicates only after per-entity and name-field access below.
    $ids = array_values($query->execute());
    $more = count($ids) > 240;
    $ids = array_slice($ids, 0, 240);
    $media = $this->entities->getStorage('media')->loadMultiple($ids);
    $fileIds = [];
    foreach ($media as $item) {
      $type = $this->types->get($item->bundle());
      if ($fid = $item->get($type['field'])->target_id) $fileIds[] = $fid;
    }
    if ($fileIds) $this->entities->getStorage('file')->loadMultiple(array_unique($fileIds));
    $rows = [];
    $last = NULL;
    foreach ($ids as $index => $id) {
      $last = (string) $id;
      try {
        $item = $media[$id];
        $row = $this->row($item);
        if ($name !== '' && mb_stripos($row['title'], $name) === FALSE) continue;
        if (!$this->matches($row, $purpose)) continue;
        $rows[] = $row;
      } catch (MediaException) { continue; }
      if (count($rows) === 24) {
        $more = $more || $index + 1 < count($ids);
        break;
      }
    }
    return ['rows' => $rows, 'nextCursor' => $more ? $last : NULL, 'scope' => $scope];
  }

  public function load(string $uuid): MediaInterface {
    $media = $this->repository->loadEntityByUuid('media', $uuid);
    if (!$media instanceof MediaInterface) throw new MediaException('media_unavailable', 404);
    $this->file($media);
    return $media;
  }

  /** Resolves existing public image references without granting library discovery or writes. */
  public function display(string $uuid): array {
    $media = $this->repository->loadEntityByUuid('media', $uuid);
    if (!$media instanceof MediaInterface || !$media->isPublished() ||
      !$media->access('view', $this->account)) throw new MediaException('media_unavailable', 404);
    $type = $this->types->get($media->bundle());
    $field = $media->get($type['field']);
    $file = $field->entity;
    if (!$field->access('view', $this->account) || !$file instanceof FileInterface ||
      !str_starts_with($file->getFileUri(), 'public://') || !$file->access('view', $this->account) ||
      !str_starts_with($file->getMimeType(), 'image/')) {
      throw new MediaException('media_unavailable', 404);
    }
    return ['src' => $this->urls->generateAbsoluteString($file->getFileUri()),
      'alt' => $field->first()?->getValue()['alt'] ?? ''];
  }

  /** Requires entity, field, file and private-download access before exposing any file metadata. */
  public function file(MediaInterface $media): FileInterface {
    $type = $this->types->get($media->bundle());
    if (!$this->policy->inScope($media, $this->account) || !$media->access('view', $this->account) ||
      !$media->get('name')->access('view', $this->account) ||
      !$media->get($type['field'])->access('view', $this->account)) throw new MediaException('media_unavailable', 404);
    $file = $media->get($type['field'])->entity;
    if (!$file instanceof FileInterface || !$file->access('view', $this->account) ||
      !$file->access('download', $this->account)) throw new MediaException('media_unavailable', 404);
    foreach (['uri', 'filename', 'filemime', 'filesize'] as $field) {
      if (!$file->get($field)->access('view', $this->account)) throw new MediaException('media_unavailable', 404);
    }
    if (str_starts_with($file->getFileUri(), 'private://')) {
      $headers = $this->modules->invokeAll('file_download', [$file->getFileUri()]);
      if (!$headers || in_array(-1, $headers, TRUE)) throw new MediaException('media_unavailable', 404);
    }
    return $file;
  }

  public function row(MediaInterface $media): array {
    $file = $this->file($media);
    $type = $this->types->get($media->bundle());
    $private = str_starts_with($file->getFileUri(), 'private://');
    $source = $private ? Url::fromRoute('xinshi_media.file', ['media_id' => $media->uuid()], ['absolute' => TRUE])->toString()
      : $this->urls->generateAbsoluteString($file->getFileUri());
    $thumb = NULL;
    if (!$private && $type['kind'] === 'image' &&
      in_array($file->getMimeType(), ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], TRUE) &&
      ($style = ImageStyle::load('thumbnail'))) {
      $thumb = $style->buildUrl($file->getFileUri());
    }
    if (!$private && $file->getMimeType() === 'image/svg+xml') $thumb = $source;
    return ['id' => (string) $media->id(), 'uuid' => $media->uuid(), 'mediaId' => $media->uuid(),
      'fileId' => $file->uuid(), 'title' => $media->label(), 'filename' => $file->getFilename(),
      'bundle' => $media->bundle(), 'kind' => $type['kind'], 'mimeType' => $file->getMimeType(),
      'size' => (int) $file->getSize(), 'private' => $private,
      'source' => $source, 'src' => $thumb ?: ($type['kind'] === 'image' && !$private ? $source : ''),
      'thumb' => $thumb, 'download' => Url::fromRoute('xinshi_media.file', ['media_id' => $media->uuid()], ['absolute' => TRUE])->toString(),
      'canRename' => $media->access('update', $this->account) && $media->get('name')->access('edit', $this->account),
      'canRemove' => $this->canRemove($media)];
  }

  public function rename(string $uuid, array $input): array {
    if (array_keys($input) !== ['name'] || !is_string($input['name']) ||
      trim($input['name']) === '' || mb_strlen($input['name']) > 255) throw new MediaException('invalid_name');
    $media = $this->load($uuid);
    if (!$media->access('update', $this->account) || !$media->get('name')->access('edit', $this->account)) {
      throw new MediaException('permission_denied', 403);
    }
    $media->setName(trim($input['name']));
    $media->setNewRevision(TRUE);
    if (count($media->validate())) throw new MediaException('invalid_media');
    $media->save();
    return $this->row($media);
  }

  /** Removing from the library never deletes files or breaks existing page and business references. */
  public function remove(string $uuid): void {
    $media = $this->load($uuid);
    if (!$this->canRemove($media)) throw new MediaException('permission_denied', 403);
    $this->database->merge('xinshi_media_hidden')->key('mid', $media->id())
      ->fields(['uid' => $this->account->id(), 'removed' => time()])->execute();
  }

  private function canRemove(MediaInterface $media): bool {
    $bundle = $media->bundle();
    return $this->policy->inScope($media, $this->account) &&
      ($this->account->hasPermission('administer media') ||
       $this->account->hasPermission('delete any ' . $bundle . ' media') ||
       $this->account->hasPermission('delete any media') ||
       ((string) $media->getOwnerId() === (string) $this->account->id() &&
        ($this->account->hasPermission('delete own ' . $bundle . ' media') || $this->account->hasPermission('delete media'))));
  }

  private function matches(array $row, string $purpose): bool {
    if ($purpose === '') return TRUE;
    $image = in_array($row['mimeType'], ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], TRUE);
    return match ($purpose) {
      'raster-image' => !$row['private'] && $row['bundle'] === 'image' && $image,
      'image-media' => $row['bundle'] === 'image' && ($image || $row['mimeType'] === 'image/svg+xml'),
      'web-image' => !$row['private'] && ($image || $row['mimeType'] === 'image/svg+xml'),
      default => FALSE,
    };
  }

}
