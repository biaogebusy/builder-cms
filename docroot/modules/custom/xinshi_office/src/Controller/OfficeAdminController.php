<?php

declare(strict_types=1);

namespace Drupal\xinshi_office\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\xinshi_office\Form\OfficeFilterForm;
use Drupal\xinshi_office\OfficeAdministration;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Administrative document discovery, bounded previews and authorized downloads. */
final class OfficeAdminController extends ControllerBase {

  public function __construct(
    private readonly OfficeAdministration $administration,
    private readonly EntityTypeManagerInterface $entities,
    private readonly DateFormatterInterface $dates,
  ) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_office.administration'),
      $container->get('entity_type.manager'), $container->get('date.formatter'));
  }

  /** Show active documents or the trash with stable pagination and owner filters. */
  public function listing(Request $request): array {
    $kind = $request->query->getString('kind');
    $name = $request->query->getString('name');
    $uid = $request->query->getString('uid');
    $deleted = $request->query->getString('deleted', '0');
    if (!in_array($kind, ['', 'word', 'sheet'], TRUE) || mb_strlen($name) > 200 ||
      ($uid !== '' && !preg_match('/^[1-9][0-9]{0,9}$/D', $uid)) ||
      !in_array($deleted, ['0', '1'], TRUE)) throw new BadRequestHttpException();
    $rows = $this->administration->listing($name, $kind, (int) $uid, $deleted === '1');
    $users = $this->entities->getStorage('user')->loadMultiple(array_unique(array_column($rows, 'uid')));
    $build = ['#cache' => ['max-age' => 0], 'filter' => $this->formBuilder()->getForm(OfficeFilterForm::class)];
    $build['documents'] = ['#type' => 'table', '#header' => [$this->t('Name'), $this->t('Type'),
      $this->t('Owner'), $this->t('Version'), $this->t('Updated'), $this->t('Operations')],
      '#empty' => $this->t('No documents match these filters.')];
    foreach ($rows as $row) {
      $id = $row['document_id'];
      $operations = ['view' => ['title' => $this->t('View'),
        'url' => Url::fromRoute('xinshi_office.admin_view', ['document_id' => $id])]];
      if ($this->currentUser()->hasPermission('delete office documents')) {
        $operation = (int) $row['deleted'] ? 'restore' : 'delete';
        $operations[$operation] = ['title' => $operation === 'delete' ? $this->t('Delete') : $this->t('Restore'),
          'url' => Url::fromRoute('xinshi_office.admin_' . $operation, ['document_id' => $id])];
      }
      $build['documents'][$id] = [
        'name' => $this->link($row['name'], 'xinshi_office.admin_view', ['document_id' => $id]),
        'kind' => ['#plain_text' => $row['kind'] === 'word' ? 'Word' : 'Excel'],
        'owner' => ['#plain_text' => (isset($users[$row['uid']]) ? $users[$row['uid']]->getAccountName()
          : $this->t('Deleted account')) . ' (' . $row['uid'] . ')'],
        'revision' => ['#plain_text' => $row['current_revision']],
        'updated' => ['#plain_text' => $this->dates->format((int) $row['updated'], 'short')],
        'operations' => ['#type' => 'operations', '#links' => $operations],
      ];
    }
    $build['pager'] = ['#type' => 'pager'];
    return $build;
  }

  /** Display one selected revision; large spreadsheets use bounded cell pages. */
  public function view(string $document_id, Request $request): array {
    $revision = $request->query->getString('revision');
    if ($revision !== '' && !preg_match('/^[1-9][0-9]{0,9}$/D', $revision)) throw new BadRequestHttpException();
    $snapshot = $this->administration->document($document_id, $revision === '' ? NULL : (int) $revision);
    $document = $snapshot['document'];
    $row = $snapshot['revision'];
    $owner = $this->entities->getStorage('user')->load($document['uid']);
    $build = ['#cache' => ['max-age' => 0], '#title' => $row['name']];
    $build['back'] = $this->link($this->t('All Office documents'), 'xinshi_office.admin');
    $build['metadata'] = ['#theme' => 'item_list', '#items' => [
      $this->t('Owner: @name (@uid)', ['@name' => $owner?->getAccountName() ?? $this->t('Deleted account'), '@uid' => $document['uid']]),
      $this->t('Type: @type. Version: @revision of @current.', ['@type' => $document['kind'] === 'word' ? 'Word' : 'Excel',
        '@revision' => $row['revision'], '@current' => $document['current_revision']]),
      (int) $document['deleted'] ? $this->t('Status: Trash') : $this->t('Status: Active'),
    ]];
    if ($this->currentUser()->hasPermission('delete office documents')) {
      $operation = (int) $document['deleted'] ? 'restore' : 'delete';
      $build['operation'] = $this->link($operation === 'delete' ? $this->t('Delete document') : $this->t('Restore document'),
        'xinshi_office.admin_' . $operation, ['document_id' => $document_id]);
    }
    $build['snapshot'] = $this->link($this->t('Download this version as JSON'), 'xinshi_office.admin_snapshot',
      ['document_id' => $document_id, 'revision' => $row['revision']]);
    $build['preview'] = ['#type' => 'details', '#title' => $this->t('Content preview'), '#open' => TRUE];
    $content = $snapshot['payload']['content'];
    if ($document['kind'] === 'word') $build['preview']['word'] = $this->word($content);
    else $build['preview']['sheet'] = $this->sheet($content, $request);
    $build['files'] = ['#type' => 'table', '#caption' => $this->t('Files for this version'),
      '#header' => [$this->t('File'), $this->t('Role'), $this->t('Bytes')], '#empty' => $this->t('No files.')];
    foreach ($this->administration->files($document_id, (int) $row['revision']) as $file) {
      $build['files'][$file['file_id']] = [
        'name' => $this->link($file['name'], 'xinshi_office.admin_download', ['document_id' => $document_id,
          'revision' => $row['revision'], 'file_id' => $file['file_id']]),
        'role' => ['#plain_text' => $file['role'] === 'original' ? $this->t('Original') : $this->t('Image')],
        'bytes' => ['#plain_text' => $file['bytes']],
      ];
    }
    $build['revisions'] = ['#type' => 'table', '#caption' => $this->t('Version history'),
      '#header' => [$this->t('Version'), $this->t('Name'), $this->t('Source'), $this->t('Created')]];
    $sources = ['ai' => $this->t('AI'), 'manual' => $this->t('Manual'), 'import' => $this->t('Import'), 'restore' => $this->t('Restore')];
    foreach ($this->administration->revisions($document_id) as $item) {
      $build['revisions'][] = ['revision' => $this->link((string) $item['revision'], 'xinshi_office.admin_view',
        ['document_id' => $document_id], ['revision' => $item['revision']]),
        'name' => ['#plain_text' => $item['name']], 'source' => ['#plain_text' => $sources[$item['source']] ?? $item['source']],
        'created' => ['#plain_text' => $this->dates->format((int) $item['created'], 'short')]];
    }
    $build['pager'] = ['#type' => 'pager'];
    return $build;
  }

  /** Export the structured revision without exposing database paths or credentials. */
  public function snapshot(string $document_id, int $revision): Response {
    $snapshot = $this->administration->document($document_id, $revision);
    return new Response($snapshot['revision']['payload_json'], 200, ['Content-Type' => 'application/json', 'Content-Disposition' =>
      'attachment; filename="office-' . $document_id . '-v' . $revision . '.json"']);
  }

  /** Download private bytes through document membership checks. */
  public function download(string $document_id, string $file_id, int $revision): BinaryFileResponse {
    $file = $this->administration->download($document_id, $revision, $file_id);
    $response = new BinaryFileResponse($file['path'], 200, ['Content-Type' => $file['mime']]);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file['name'], 'office-file');
    return $response;
  }

  private function link(mixed $title, string $route, array $parameters = [], array $query = []): array {
    return ['#type' => 'link', '#title' => $title, '#url' => Url::fromRoute($route, $parameters, ['query' => $query])];
  }

  private function word(array $node): array {
    if ($node['type'] === 'text') return ['#plain_text' => $node['text']];
    if ($node['type'] === 'image') return ['#plain_text' => $this->t('[Image: @alt]', ['@alt' => $node['attrs']['alt'] ?? ''])];
    $tags = ['doc' => 'div', 'paragraph' => 'p', 'bulletList' => 'ul', 'orderedList' => 'ol',
      'listItem' => 'li', 'table' => 'table', 'tableRow' => 'tr', 'tableCell' => 'td', 'tableHeader' => 'th', 'hardBreak' => 'br'];
    $tag = $node['type'] === 'heading' ? 'h' . $node['attrs']['level'] : ($tags[$node['type']] ?? 'span');
    $build = ['#type' => 'html_tag', '#tag' => $tag];
    foreach ($node['content'] ?? [] as $child) $build[] = $this->word($child);
    return $build;
  }

  private function sheet(array $content, Request $request): array {
    $rows = [];
    foreach ($content['sheets'] as $sheet) {
      foreach ($sheet['cells'] as $cell) $rows[] = [$sheet['name'], $cell];
    }
    $offset = $request->query->getInt('cells');
    if ($offset < 0 || $offset > 50000) throw new BadRequestHttpException();
    $build['notice'] = ['#plain_text' => $this->t('Cells @start–@end of @total. Date system: @system. Formatting is retained in the JSON download.',
      ['@start' => $rows ? $offset + 1 : 0, '@end' => min(count($rows), $offset + 100),
        '@total' => count($rows), '@system' => $content['dateSystem']])];
    $build['cells'] = ['#type' => 'table', '#header' => [$this->t('Worksheet'), $this->t('Cell'), $this->t('Type'), $this->t('Value or formula')]];
    foreach (array_slice($rows, $offset, 100) as [$name, $cell]) {
      $column = $cell['column'] + 1;
      $address = '';
      while ($column > 0) { $column--; $address = chr(65 + $column % 26) . $address; $column = intdiv($column, 26); }
      $value = $cell['value'] ?? [];
      $text = $value['formula'] ?? $value['value'] ?? '';
      if (is_bool($text)) $text = $text ? 'TRUE' : 'FALSE';
      $build['cells'][] = array_map(static fn($value) => ['#plain_text' => (string) $value],
        [$name, $address . ($cell['row'] + 1), $value['type'] ?? '', $text]);
    }
    foreach (['previous' => $offset - 100, 'next' => $offset + 100] as $direction => $target) {
      if ($target < 0 || $target >= count($rows)) continue;
      $query = array_intersect_key($request->query->all(), array_flip(['revision', 'page']));
      $build[$direction] = $this->link($direction === 'next' ? $this->t('Next cells') : $this->t('Previous cells'),
        'xinshi_office.admin_view', ['document_id' => $request->attributes->get('document_id')], $query + ['cells' => $target]);
    }
    return $build;
  }

}
