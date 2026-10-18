<?php

declare(strict_types=1);

namespace Xinshi\ChatMigration;

/** Private, immutable artifacts for the explicitly invoked offline migration. */
final class Archive {

  public const ROW_BYTES = 128 * 1024 * 1024;

  /** Fail before reading or creating artifacts under a public or linked directory. */
  public static function directory(string $path, bool $create = FALSE): string {
    if (!str_starts_with($path, '/') || str_contains($path, '://') || is_link($path)) {
      throw new \RuntimeException('private_directory_required');
    }
    $parent = realpath($create ? dirname($path) : $path);
    if (!$parent || ($create && file_exists($path))) {
      throw new \RuntimeException('directory_unavailable');
    }
    $resolved = $create ? $parent . '/' . basename($path) : $parent;
    $public = realpath(DRUPAL_ROOT);
    if ($public && ($resolved === $public || str_starts_with($resolved, $public . '/'))) {
      throw new \RuntimeException('public_directory_rejected');
    }
    if ($create && !mkdir($resolved, 0700)) {
      throw new \RuntimeException('directory_unavailable');
    }
    if ($create) {
      self::syncDirectory($parent);
    }
    if (!is_dir($resolved) || (fileperms($resolved) & 0077) !== 0) {
      throw new \RuntimeException('private_directory_required');
    }
    return $resolved;
  }

  /** Open a new artifact without replacing an earlier batch. */
  public static function create(string $path) {
    $handle = fopen($path, 'xb');
    if (!$handle || !chmod($path, 0600)) {
      throw new \RuntimeException('artifact_unavailable');
    }
    return $handle;
  }

  /** Write the complete buffer; short writes must never produce a completed manifest. */
  public static function write($handle, string $bytes): void {
    for ($offset = 0, $length = strlen($bytes); $offset < $length;) {
      $written = fwrite($handle, substr($bytes, $offset));
      if (!$written) {
        throw new \RuntimeException('artifact_write_failed');
      }
      $offset += $written;
    }
  }

  /** Synchronize before publishing a manifest or a progress checkpoint. */
  public static function sync($handle): void {
    if (!fflush($handle) || !fsync($handle)) {
      throw new \RuntimeException('artifact_sync_failed');
    }
  }

  /** A durable file is not published until its directory entry has also reached storage. */
  public static function syncDirectory(string $directory): void {
    $handle = fopen($directory, 'r');
    if (!$handle) {
      throw new \RuntimeException('directory_sync_failed');
    }
    try {
      if (!fsync($handle)) {
        throw new \RuntimeException('directory_sync_failed');
      }
    }
    finally {
      fclose($handle);
    }
  }

  /** Keep objects and lists distinct, with deterministic map ordering when requested. */
  public static function json(mixed $value): string {
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE |
      JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
  }

  /** Raw SQL cells are encoded separately, retaining invalid UTF-8 and empty bytes. */
  public static function pack(array $rows): array {
    return array_map(static fn(array $items) => array_map(static function (array $row): array {
      ksort($row, SORT_STRING);
      return array_map(static fn($cell) => $cell === NULL ? NULL : base64_encode((string) $cell), $row);
    }, $items), $rows);
  }

  /** Decode the exact SQL values; no entity defaults or processed HTML participate. */
  public static function unpack(array $rows): array {
    return array_map(static fn(array $items) => array_map(static function (array $row): array {
      return array_map(static function ($cell) {
        if ($cell === NULL) {
          return NULL;
        }
        if (!is_string($cell) || ($decoded = base64_decode($cell, TRUE)) === FALSE ||
          base64_encode($decoded) !== $cell) {
          throw new \RuntimeException('invalid_sql_snapshot');
        }
        return $decoded;
      }, $row);
    }, $items), $rows);
  }

  /** Read bounded lines and reject partial files, links, and non-private artifacts. */
  public static function lines(string $path, ?string $sha256 = NULL): \Generator {
    if (is_link($path) || !is_file($path) || (fileperms($path) & 0077) !== 0) {
      throw new \RuntimeException('private_artifact_required');
    }
    $handle = fopen($path, 'rb');
    if (!$handle) {
      throw new \RuntimeException('artifact_unavailable');
    }
    $before = fstat($handle);
    $hash = hash_init('sha256');
    try {
      // fgets reserves its requested length even for a tiny record under PHP's memory limit.
      $line = '';
      while (($chunk = fgets($handle, 65537)) !== FALSE) {
        $line .= $chunk;
        if (strlen($line) > self::ROW_BYTES) {
          throw new \RuntimeException('invalid_artifact_line');
        }
        if (!str_ends_with($line, "\n")) {
          continue;
        }
        if (trim($line) === '') {
          throw new \RuntimeException('invalid_artifact_line');
        }
        hash_update($hash, $line);
        yield substr($line, 0, -1);
        $line = '';
      }
      if ($line !== '') {
        throw new \RuntimeException('invalid_artifact_line');
      }
      $after = fstat($handle);
      if (!feof($handle) || $before['size'] !== $after['size'] ||
        $before['mtime'] !== $after['mtime'] || $before['ctime'] !== $after['ctime'] ||
        ($sha256 !== NULL && !hash_equals($sha256, hash_final($hash)))) {
        throw new \RuntimeException('artifact_changed');
      }
    }
    finally {
      fclose($handle);
    }
  }

  /** Publish a small manifest last; failed exports keep only partial artifacts. */
  public static function publish(string $path, array $value): void {
    $bytes = self::json($value) . "\n";
    if (strlen($bytes) > 8 * 1024 * 1024) {
      throw new \RuntimeException('manifest_too_large');
    }
    $partial = $path . '.' . bin2hex(random_bytes(8)) . '.partial';
    $handle = self::create($partial);
    try {
      self::write($handle, $bytes);
      self::sync($handle);
    }
    finally {
      fclose($handle);
    }
    if (file_exists($path) || !rename($partial, $path)) {
      throw new \RuntimeException('manifest_publish_failed');
    }
    self::syncDirectory(dirname($path));
  }

  /** Load only a complete, private manifest with a fixed bounded size. */
  public static function manifest(string $path): array {
    if (is_link($path) || !is_file($path) || (fileperms($path) & 0077) !== 0 ||
      filesize($path) > 8 * 1024 * 1024) {
      throw new \RuntimeException('invalid_manifest');
    }
    $value = json_decode(file_get_contents($path), TRUE, 64, JSON_THROW_ON_ERROR);
    if (!is_array($value)) {
      throw new \RuntimeException('invalid_manifest');
    }
    return $value;
  }

}
