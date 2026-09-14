<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\file\FileInterface;
use Drupal\xinshi_knowledge\Knowledge;
use GuzzleHttp\ClientInterface;

/** Bounded, deterministic extraction. No model calls or document-supplied URLs. */
class AttachmentParser {

  private const MAX_RESPONSE_BYTES = 8 * 1024 * 1024;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /** @return array{hash: string, segments: array} */
  public function parse(FileInterface $file): array {
    $uri = $file->getFileUri();
    clearstatcache(TRUE, $uri);
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (!str_starts_with($uri, 'private://') ||
        !in_array($extension, ['txt', 'doc', 'docx', 'xls', 'xlsx', 'pdf'], TRUE)) {
      throw new \DomainException('unsupported');
    }
    if (!is_file($uri) || !is_readable($uri)) {
      throw new \DomainException('file_missing');
    }
    if (filesize($uri) > Knowledge::MAX_FILE_BYTES) {
      throw new \DomainException('too_large');
    }
    $hash = hash_file('sha256', $uri);
    if ($extension === 'txt') {
      $segments = $this->plainText(file_get_contents($uri));
    }
    else {
      $base = rtrim((string) $this->configFactory->get('xinshi_knowledge.settings')->get('tika_url'), '/');
      if (!self::validServerUrl($base)) {
        throw new \DomainException('not_configured');
      }
      $stream = fopen($uri, 'rb');
      $deadline = microtime(TRUE) + 60;
      try {
        $response = $this->httpClient->request('PUT', $base . '/tika/xml', [
          'connect_timeout' => 5, 'timeout' => 60, 'read_timeout' => 60,
          'allow_redirects' => FALSE, 'http_errors' => FALSE, 'stream' => TRUE,
          'body' => $stream,
          'headers' => [
            'Accept' => 'text/xml', 'Content-Type' => 'application/octet-stream',
          ],
        ]);
        $body = $response->getBody();
        try {
          if ($response->getStatusCode() !== 200) {
            throw new \DomainException(match ($response->getStatusCode()) {
              400, 415, 422 => 'invalid_document',
              413 => 'too_large',
              default => 'parser_unavailable',
            });
          }
          if ((int) $response->getHeaderLine('Content-Length') > self::MAX_RESPONSE_BYTES) {
            throw new \DomainException('too_large');
          }
          $xml = '';
          while (!$body->eof()) {
            $xml .= $body->read(65536);
            if (microtime(TRUE) > $deadline) {
              throw new \DomainException('parser_unavailable');
            }
            if (strlen($xml) > self::MAX_RESPONSE_BYTES) {
              throw new \DomainException('too_large');
            }
          }
        }
        finally {
          $body->close();
        }
        $segments = $this->xhtml($xml, $extension);
      }
      finally {
        if (is_resource($stream)) {
          fclose($stream);
        }
      }
    }
    if (hash_file('sha256', $uri) !== $hash) {
      throw new \DomainException('source_changed');
    }
    $characters = array_sum(array_map(static fn(array $segment): int => mb_strlen($segment['text']), $segments));
    if ($characters === 0) {
      throw new \DomainException($extension === 'pdf' ? 'needs_ocr' : 'no_text');
    }
    if ($characters > Knowledge::MAX_TEXT_CHARACTERS || count($segments) > 10000) {
      throw new \DomainException('too_large');
    }
    return ['hash' => $hash, 'segments' => $segments];
  }

  public static function validServerUrl(string $url): bool {
    $parts = parse_url($url);
    return $parts && in_array($parts['scheme'] ?? '', ['http', 'https'], TRUE) &&
      !empty($parts['host']) && !isset($parts['pass']) &&
      !isset($parts['user']) && !isset($parts['query']) && !isset($parts['fragment']);
  }

  private function plainText(string $bytes): array {
    $encoding = NULL;
    foreach (["\xEF\xBB\xBF" => 'UTF-8', "\xFF\xFE" => 'UTF-16LE', "\xFE\xFF" => 'UTF-16BE'] as $bom => $candidate) {
      if (str_starts_with($bytes, $bom)) {
        $encoding = $candidate;
        $bytes = substr($bytes, strlen($bom));
        break;
      }
    }
    $encoding ??= mb_detect_encoding($bytes, ['UTF-8', 'GB18030'], TRUE);
    if (!$encoding || !mb_check_encoding($bytes, $encoding)) {
      throw new \DomainException('invalid_encoding');
    }
    $text = mb_convert_encoding($bytes, 'UTF-8', $encoding);
    if (preg_match('/[\x00-\x08\x0B\x0E-\x1F]/', $text)) {
      throw new \DomainException('invalid_document');
    }
    $segments = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $i => $line) {
      if (trim($line) !== '') {
        $segments[] = ['location' => ['line' => $i + 1], 'text' => trim($line)];
      }
    }
    return $segments;
  }

  /** Keep only body text and structural locations; never render Tika markup. */
  private function xhtml(string $xml, string $extension): array {
    if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
      throw new \DomainException('invalid_document');
    }
    $previous = libxml_use_internal_errors(TRUE);
    try {
      $document = new \DOMDocument();
      if (!$document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
        throw new \DomainException('invalid_document');
      }
      $xpath = new \DOMXPath($document);
      foreach ($xpath->query('//*[local-name()="script" or local-name()="style" or local-name()="head"]') as $hidden) {
        $hidden->parentNode->removeChild($hidden);
      }
      $body = $xpath->query('//*[local-name()="body"]')->item(0);
      if (!$body) {
        throw new \DomainException('invalid_document');
      }
      $segments = [];
      $pages = $xpath->query('.//*[local-name()="div" and @class="page"]', $body);
      if ($extension === 'pdf' && $pages->length) {
        foreach ($pages as $i => $page) {
          $text = $this->nodeText($page);
          if ($text !== '') {
            $segments[] = ['location' => ['page' => $i + 1], 'text' => $text];
          }
        }
        return $segments;
      }
      $spreadsheet = in_array($extension, ['xls', 'xlsx'], TRUE);
      $heading = '';
      $row = $paragraph = $table = 0;
      foreach ($xpath->query('.//*[local-name()="h1" or local-name()="h2" or local-name()="h3" or local-name()="h4" or local-name()="h5" or local-name()="h6" or local-name()="p" or local-name()="li" or local-name()="tr"]', $body) as $block) {
        // Cell paragraphs are included in their table row, once.
        if ($block->localName !== 'tr' && $xpath->query('ancestor::*[local-name()="tr"]', $block)->length) {
          continue;
        }
        if ($block->localName !== 'li' && $xpath->query('ancestor::*[local-name()="li"]', $block)->length) {
          continue;
        }
        $text = $this->nodeText($block);
        if ($text === '') {
          continue;
        }
        if (preg_match('/^h[1-6]$/', $block->localName)) {
          $heading = $text;
          $row = 0;
        }
        if ($block->localName === 'tr') {
          $cells = [];
          foreach ($xpath->query('./*[local-name()="td" or local-name()="th"]', $block) as $cell) {
            $cells[] = $this->nodeText($cell);
          }
          $text = implode(' | ', $cells);
          $location = $spreadsheet ? ['sheet' => $heading, 'tableRow' => ++$row]
            : ['section' => $heading, 'tableRow' => ++$table];
        }
        else {
          $location = ['section' => $heading, 'paragraph' => ++$paragraph];
        }
        $segments[] = ['location' => array_filter($location, static fn($value) => $value !== ''), 'text' => $text];
      }
      if (!$segments && ($text = $this->nodeText($body)) !== '') {
        $segments[] = ['location' => ['paragraph' => 1], 'text' => $text];
      }
      return $segments;
    }
    finally {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
    }
  }

  private function nodeText(\DOMNode $node): string {
    $text = '';
    foreach ($node->childNodes as $child) {
      if ($child instanceof \DOMText) {
        $text .= $child->textContent;
      }
      else {
        $text .= $this->nodeText($child);
        if (in_array($child->localName, ['p', 'br', 'div', 'tr', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], TRUE)) {
          $text .= "\n";
        }
        elseif (in_array($child->localName, ['td', 'th'], TRUE)) {
          $text .= ' | ';
        }
      }
    }
    return trim(preg_replace('/[\p{Z}\t]+/u', ' ', $text));
  }

}
