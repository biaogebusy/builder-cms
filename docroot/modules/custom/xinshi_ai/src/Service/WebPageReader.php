<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use League\HTMLToMarkdown\HtmlConverter;

/** Preserve source markup that a provider's plain-text conversion can lose. */
class WebPageReader {

  public function __construct(private readonly PublicHttpClientFactory $http) {}

  /** Read only public HTML, without credentials, scripts, or remote subresources. */
  public function read(string $url): array {
    $response = $this->http->create(['timeout' => 3], 1048576)->request('GET', $url, [
      'headers' => ['Accept' => 'text/html, application/xhtml+xml'],
      'connect_timeout' => 2, 'http_errors' => FALSE,
    ]);
    $type = $response->getHeaderLine('Content-Type');
    if ($response->getStatusCode() !== 200 ||
      !preg_match('~^(?:text/html|application/xhtml\+xml)(?:;|$)~i', $type)) {
      throw new \RuntimeException('Original HTML is unavailable.');
    }
    $html = (string) $response->getBody();
    if (preg_match('/charset\s*=\s*["\']?([\w-]+)/i', $type, $encoding)) {
      $html = mb_convert_encoding($html, 'UTF-8', $encoding[1]);
    }
    if (!mb_check_encoding($html, 'UTF-8')) {
      throw new \RuntimeException('Original HTML encoding is unsupported.');
    }
    $document = new \DOMDocument();
    if (!@$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
      throw new \RuntimeException('Original HTML could not be parsed.');
    }
    $title = trim($document->getElementsByTagName('title')->item(0)?->textContent ?? '');
    $body = $document->getElementsByTagName('body')->item(0);
    if (!$body) {
      throw new \RuntimeException('Original HTML has no body.');
    }
    // Preserve unsupported formatting, including nested sup/sub and complex tables.
    $converter = new HtmlConverter(['header_style' => 'atx', 'strip_tags' => FALSE,
      'remove_nodes' => 'script style template iframe object embed', 'hard_break' => TRUE]);
    $content = trim($converter->convert($document->saveHTML($body)));
    if ($content === '') {
      throw new \RuntimeException('Original HTML has no readable content.');
    }
    return ['title' => $title, 'content' => $content, 'contentFormat' => 'html_markdown'];
  }

}
