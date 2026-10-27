<?php

declare(strict_types=1);

namespace Drupal\xinshi_media;

use enshrined\svgSanitize\Sanitizer;
use enshrined\svgSanitize\data\TagInterface;

/** Validates source bytes before a file reaches its configured storage location. */
final class MediaValidator {

  public function validate(string $bytes, string $extension, array $type): array {
    if ($bytes === '' || strlen($bytes) > $type['maxBytes']) throw new MediaException('file_too_large', 413);
    if (!in_array($extension, $type['extensions'], TRUE)) throw new MediaException('unsupported_format', 415);
    if ($type['kind'] === 'vector') return [$this->svg($bytes), 'image/svg+xml'];
    $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if ($type['kind'] === 'image') {
      $image = @getimagesizefromstring($bytes);
      $expected = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp'][$extension] ?? NULL;
      if (!$image || !$expected || $image['mime'] !== $expected || $mime !== $expected ||
        $image[0] <= 0 || $image[1] <= 0 || $image[0] * $image[1] > 33554432) {
        throw new MediaException('invalid_image', 422);
      }
      return [$bytes, $expected];
    }
    if ($type['kind'] === 'document') {
      if ($extension === 'pdf' && str_starts_with($bytes, '%PDF-') && $mime === 'application/pdf') return [$bytes, $mime];
      if (in_array($extension, ['txt', 'csv', 'md'], TRUE) && !str_contains($bytes, "\0") && mb_check_encoding($bytes, 'UTF-8')) {
        return [$bytes, $extension === 'csv' ? 'text/csv' : 'text/plain'];
      }
      if (in_array($extension, ['docx', 'xlsx', 'pptx'], TRUE)) return [$bytes, $this->office($bytes, $extension)];
      throw new MediaException('invalid_document', 422);
    }
    $mimes = [
      'mp3' => ['audio/mpeg'], 'm4a' => ['audio/mp4', 'video/mp4', 'audio/x-m4a'],
      'wav' => ['audio/x-wav', 'audio/wav', 'audio/vnd.wave'], 'ogg' => ['audio/ogg', 'application/ogg'],
      'aac' => ['audio/aac', 'audio/x-hx-aac-adts'], 'mp4' => ['video/mp4'], 'webm' => ['video/webm'],
    ];
    if (!in_array($mime, $mimes[$extension] ?? [], TRUE)) throw new MediaException('invalid_media_bytes', 422);
    return [$bytes, $extension === 'm4a' ? 'audio/mp4' : $mime];
  }

  /** Allows static SVG only, with local fragment references and no embedded active documents. */
  public function svg(string $bytes): string {
    if ($bytes === '' || strlen($bytes) > 1048576 || preg_match('/<!DOCTYPE|<!ENTITY/i', $bytes)) {
      throw new MediaException('invalid_svg', 422);
    }
    $sanitizer = new Sanitizer();
    $sanitizer->removeRemoteReferences(TRUE);
    $sanitizer->setAllowedTags(new class implements TagInterface {
      public static function getTags(): array {
        return ['svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc', 'path', 'rect', 'circle',
          'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'textPath', 'clipPath',
          'mask', 'pattern', 'linearGradient', 'radialGradient', 'stop'];
      }
    });
    $clean = $sanitizer->sanitize($bytes);
    if (!is_string($clean) || $clean === '') throw new MediaException('invalid_svg', 422);
    $previous = libxml_use_internal_errors(TRUE);
    try {
      $document = new \DOMDocument();
      if (!$document->loadXML($clean, LIBXML_NONET) || $document->documentElement->localName !== 'svg' ||
        $document->documentElement->namespaceURI !== 'http://www.w3.org/2000/svg') throw new MediaException('invalid_svg', 422);
      foreach ((new \DOMXPath($document))->query('//processing-instruction()') as $instruction) {
        $instruction->parentNode->removeChild($instruction);
      }
      foreach ($document->getElementsByTagName('*') as $element) {
        foreach (iterator_to_array($element->attributes) as $attribute) {
          $name = strtolower($attribute->localName);
          $value = trim($attribute->value);
          if ($name === 'style' || $name === 'base' || str_contains($value, '\\') ||
            str_starts_with($name, 'on') ||
            ($name === 'href' && !preg_match('/^#[a-zA-Z_][a-zA-Z0-9_.:-]*$/D', $value)) ||
            (stripos($value, 'url(') !== FALSE && !preg_match('/^url\(#[a-zA-Z_][a-zA-Z0-9_.:-]*\)$/D', $value))) {
            $element->removeAttributeNode($attribute);
          }
        }
      }
      return $document->saveXML($document->documentElement);
    } finally {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
    }
  }

  private function office(string $bytes, string $extension): string {
    $path = tempnam(sys_get_temp_dir(), 'media-office-');
    try {
      file_put_contents($path, $bytes);
      $zip = new \ZipArchive();
      if ($zip->open($path) !== TRUE) throw new MediaException('invalid_document', 422);
      try {
        $main = ['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml'][$extension];
        if ($zip->numFiles > 10000 || $zip->locateName('[Content_Types].xml') === FALSE || $zip->locateName($main) === FALSE) {
          throw new MediaException('invalid_document', 422);
        }
        $total = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
          $entry = $zip->statIndex($index);
          $total += $entry['size'];
          if ($total > 209715200 || preg_match('@(^|/)\.\.(/|$)|vbaProject|^/@i', $entry['name']) ||
            ($entry['encryption_method'] ?? 0) !== 0) throw new MediaException('invalid_document', 422);
        }
      } finally { $zip->close(); }
    } finally { if ($path && file_exists($path)) unlink($path); }
    return 'application/vnd.openxmlformats-officedocument.' . [
      'docx' => 'wordprocessingml.document', 'xlsx' => 'spreadsheetml.sheet', 'pptx' => 'presentationml.presentation',
    ][$extension];
  }

}
