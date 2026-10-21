<?php

declare(strict_types=1);

namespace Drupal\xinshi_office;

use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PrivateStream;

/** Immutable private bytes. No Office conversion or remote resource fetching occurs here. */
final class OfficeFiles {

  public function __construct(
    private readonly Connection $database,
    private readonly FileSystemInterface $filesystem,
    private readonly OfficeAccess $access,
    private readonly OfficeTypes $types,
  ) {}

  /** Stores a bounded upload under a caller-generated ID for safe response-loss recovery. */
  public function upload(\stdClass $metadata, string $temporary_path): array {
    OfficeInput::object($metadata, ['fileId', 'kind', 'role', 'name']);
    OfficeInput::uuid($metadata->fileId);
    OfficeInput::text($metadata->name, 200, TRUE);
    if (preg_match('/[\/\\\\\x00-\x1F\x7F]/', $metadata->name) ||
      !is_string($metadata->kind) || !preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $metadata->kind) ||
      !in_array($metadata->role, ['original', 'image'], TRUE)) {
      throw new OfficeException('invalid_file');
    }
    $uid = $this->access->owner();
    $size = is_file($temporary_path) ? filesize($temporary_path) : FALSE;
    if (!$size || $size > ($metadata->role === 'image' ? 5242880 : 10485760)) {
      throw new OfficeException('file_limit', 413);
    }
    $sha = hash_file('sha256', $temporary_path);
    $properties = $metadata->role === 'image' ? $this->image($temporary_path) :
      $this->original($temporary_path, $metadata->kind, $metadata->name);
    $row = ['file_id' => $metadata->fileId, 'uid' => $uid, 'kind' => $metadata->kind,
      'role' => $metadata->role, 'name' => $metadata->name, 'bytes' => $size, 'sha256' => $sha] + $properties;
    $transaction = $this->database->startTransaction();
    try {
      $this->access->lock($uid);
      $existing = $this->database->select('xinshi_office_file', 'f')->fields('f')
        ->condition('file_id', $metadata->fileId)->execute()->fetchAssoc();
      if ($existing) {
        foreach ($row as $key => $value) {
          if ((string) $existing[$key] !== (string) $value) {
            throw new OfficeException('file_id_conflict', 409);
          }
        }
        $result = $this->describe($existing);
      }
      else {
        $this->access->write($metadata->kind);
        $this->access->quota($uid, $size);
        if (!PrivateStream::basePath()) {
          throw new OfficeException('private_storage_unavailable', 503);
        }
        $directory = 'private://xinshi-office/' . $uid;
        if (!$this->filesystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
          throw new OfficeException('private_storage_unavailable', 503);
        }
        $uri = $directory . '/' . $metadata->fileId;
        $this->copy($temporary_path, $uri, $sha, $size);
        $row += ['uri' => $uri, 'created' => time()];
        $this->database->insert('xinshi_office_file')->fields($row)->execute();
        $result = $this->describe($row);
      }
      unset($transaction);
      return $result;
    }
    catch (\Throwable $error) {
      if (isset($transaction)) {
        $transaction->rollBack();
        unset($transaction);
      }
      // Keep any written bytes if database commit was uncertain. A retry never overwrites them.
      throw $error;
    }
  }

  /** Returns only the current owner's metadata; a file ID alone grants no access. */
  public function metadata(string $id): array {
    return $this->describe($this->owned($id));
  }

  /** Resolves verified bytes after the document service has checked revision membership. */
  public function download(string $id): array {
    $row = $this->owned($id);
    $path = $this->filesystem->realpath($row['uri']);
    if (!$path || !is_file($path) || filesize($path) !== (int) $row['bytes'] ||
      !hash_equals($row['sha256'], hash_file('sha256', $path))) {
      throw new OfficeException('file_unavailable', 503);
    }
    return $this->describe($row) + ['path' => $path];
  }

  private function owned(string $id): array {
    OfficeInput::uuid($id);
    $uid = $this->access->owner();
    $row = $this->database->select('xinshi_office_file', 'f')->fields('f')
      ->condition('file_id', $id)->condition('uid', $uid)->execute()->fetchAssoc();
    if (!$row) {
      throw new OfficeException('file_unavailable', 404);
    }
    return $row;
  }

  private function describe(array $row): array {
    return ['fileId' => $row['file_id'], 'kind' => $row['kind'], 'role' => $row['role'],
      'name' => $row['name'], 'sha256' => $row['sha256'], 'bytes' => (int) $row['bytes'],
      'mime' => $row['mime'], 'width' => (int) $row['width'], 'height' => (int) $row['height']];
  }

  private function image(string $path): array {
    $size = @getimagesize($path);
    if (!$size || !in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], TRUE)) {
      throw new OfficeException('invalid_image');
    }
    [$width, $height] = $size;
    if ($width < 1 || $height < 1 || $width > 8192 || $height > 8192 || $width * $height > 16000000) {
      throw new OfficeException('image_limit');
    }
    if (!function_exists('imagecreatefromstring')) {
      throw new OfficeException('image_validation_unavailable', 503);
    }
    $image = @imagecreatefromstring(file_get_contents($path));
    if (!$image || imagesx($image) !== $width || imagesy($image) !== $height) {
      throw new OfficeException('invalid_image');
    }
    imagedestroy($image);
    return ['width' => $width, 'height' => $height, 'mime' => $size['mime']];
  }

  private function original(string $path, mixed $kind, string $name): array {
    $format = $this->types->get($kind)->fileFormat();
    if (!str_ends_with(strtolower($name), $format['extension'])) {
      throw new OfficeException('invalid_file');
    }
    if (!class_exists(\ZipArchive::class)) {
      throw new OfficeException('file_validation_unavailable', 503);
    }
    $zip = new \ZipArchive();
    if ($zip->open($path, \ZipArchive::RDONLY) !== TRUE) {
      throw new OfficeException('invalid_file');
    }
    try {
      if ($zip->numFiles < 3 || $zip->numFiles > 2000) {
        throw new OfficeException('file_limit');
      }
      $bytes = 0;
      $names = [];
      for ($i = 0; $i < $zip->numFiles; $i++) {
        $part = $zip->statIndex($i);
        $key = $part['name'];
        if (strlen($key) > 512 || preg_match('/(^\/|\\\\|(^|\/)\.\.?($|\/)|[\x00-\x1F])/', $key) ||
          isset($names[strtolower($key)]) || ($part['encryption_method'] ?? 0) !== 0 ||
          preg_match('/vbaProject|activeX|macros/i', $key)) {
          throw new OfficeException('invalid_file');
        }
        $names[strtolower($key)] = TRUE;
        $bytes += $part['size'];
        if ($part['size'] > 20971520 || $bytes > 104857600) {
          throw new OfficeException('file_limit');
        }
      }
      if (!isset($names['[content_types].xml'], $names[strtolower($format['part'])], $names['_rels/.rels'])) {
        throw new OfficeException('invalid_file');
      }
      $types = $zip->getFromName('[Content_Types].xml', 1048577);
      if (!is_string($types) || strlen($types) > 1048576 ||
        preg_match('/<!DOCTYPE|<!ENTITY|macroEnabled|vbaProject|activeX/i', $types)) {
        throw new OfficeException('invalid_file');
      }
      OfficeInput::text($types, 1048576);
      $xml = new \DOMDocument();
      if (!@$xml->loadXML($types, LIBXML_NONET) ||
        $xml->documentElement?->namespaceURI !== 'http://schemas.openxmlformats.org/package/2006/content-types') {
        throw new OfficeException('invalid_file');
      }
      $valid = FALSE;
      foreach ($xml->getElementsByTagName('Override') as $entry) {
        if (preg_match('/macroEnabled|vbaProject|activeX/i', $entry->getAttribute('ContentType'))) {
          throw new OfficeException('invalid_file');
        }
        if ($entry->getAttribute('PartName') === '/' . $format['part'] &&
          $entry->getAttribute('ContentType') === $format['contentType']) {
          $valid = TRUE;
        }
      }
      if (!$valid) {
        throw new OfficeException('invalid_file');
      }
    }
    finally {
      $zip->close();
    }
    return ['width' => 0, 'height' => 0, 'mime' => $format['mime']];
  }

  private function copy(string $source, string $uri, string $sha, int $size): void {
    if (!file_exists($uri)) {
      $target = @fopen($uri, 'x+b');
      $input = fopen($source, 'rb');
      if (!$target || !$input) {
        if (is_resource($target)) fclose($target);
        if (is_resource($input)) fclose($input);
        throw new OfficeException('private_storage_unavailable', 503);
      }
      try {
        if (stream_copy_to_stream($input, $target, $size + 1) !== $size || !fflush($target)) {
          throw new OfficeException('private_storage_unavailable', 503);
        }
      }
      finally {
        fclose($input);
        fclose($target);
      }
    }
    clearstatcache(TRUE, $uri);
    if (filesize($uri) !== $size || !hash_equals($sha, hash_file('sha256', $uri))) {
      throw new OfficeException('file_id_conflict', 409);
    }
  }

}
