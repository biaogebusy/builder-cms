<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_knowledge\Knowledge;
use Drupal\xinshi_knowledge\Service\AttachmentExtraction;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** A node-local status/retry form; the normal Media Library handles uploads. */
final class DocumentStatusForm extends FormBase {

  public function __construct(
    private readonly AttachmentExtraction $extraction,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_knowledge.extraction'), $container->get('entity_type.manager'));
  }

  public function getFormId(): string {
    return 'xinshi_knowledge_document_status';
  }

  public function access(NodeInterface $node, AccountInterface $account): AccessResult {
    return AccessResult::allowedIf($node->bundle() === Knowledge::NODE)
      ->andIf(AccessResult::allowedIfHasPermission($account, 'view xinshi knowledge'))
      ->andIf($node->access('update', $account, TRUE))->addCacheableDependency($node);
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $form_state->set('node_id', $node->id());
    $form_state->set('langcode', $node->language()->getId());
    $form['#cache']['max-age'] = 0;
    $form['description'] = ['#markup' => '<p>' . $this->t('保存附件后会自动进入后台解析队列；解析完成后仍需等待检索索引更新。资料及附件均已发布、可查看并解析成功后，AI 才能读取。') . '</p>'];
    $form['files'] = ['#type' => 'table', '#header' => [$this->t('附件'), $this->t('解析状态'), $this->t('说明')],
      '#empty' => $this->t('尚未添加附件。请在编辑页面通过媒体库上传文档。')];
    $statusLabels = ['pending' => '等待处理', 'processing' => '处理中', 'ready' => '解析完成', 'failed' => '解析失败'];
    $reasons = [
      'not_configured' => '未配置解析服务。', 'parser_unavailable' => '解析服务暂时不可用，请稍后重试。',
      'needs_ocr' => 'PDF 未提取到文字，请检查是否为扫描件并先进行 OCR。',
      'no_text' => '文件中未提取到文字。', 'too_large' => '文件或解析文本超出限制，请拆分文件。',
      'invalid_document' => '文件格式无效、损坏或已加密，请检查后重新上传。',
      'invalid_encoding' => '文本编码无法识别，请另存为 UTF-8 后上传。',
      'source_changed' => '解析期间文件发生变化，请重新解析。',
      'file_missing' => '原文件不存在或无法读取，请重新上传。',
      'unsupported' => '请使用支持的格式并保存到私有文件目录。',
    ];
    foreach ($this->readableMedia($node) as $media) {
      $file = $this->extraction->file($media);
      $state = $this->extraction->state((int) $media->id());
      $status = $state && $file && $state['source_key'] === $this->extraction->sourceKey($media, $file)
        ? $state['status'] : 'pending';
      $form['files'][$media->id()] = [
        'name' => ['#plain_text' => $file?->getFilename() ?? $media->label()],
        'status' => ['#plain_text' => $this->t($statusLabels[$status] ?? '等待处理')],
        'reason' => ['#plain_text' => $this->t($reasons[$state['error'] ?? ''] ?? '')],
      ];
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['retry'] = ['#type' => 'submit', '#value' => $this->t('重新解析附件')];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache([$form_state->get('node_id')]);
    $node = $storage->load($form_state->get('node_id'));
    $language = $form_state->get('langcode');
    if (!$node instanceof NodeInterface || !$node->hasTranslation($language) ||
        !$this->access($node->getTranslation($language), $this->currentUser())->isAllowed()) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }
    $count = 0;
    foreach ($this->readableMedia($node->getTranslation($language)) as $media) {
      $this->extraction->schedule($media, TRUE);
      $count++;
    }
    $this->messenger()->addStatus($this->t('已将 @count 个附件加入解析队列。', ['@count' => $count]));
    $form_state->setRebuild();
  }

  /** Do not disclose filenames through the editor status page. */
  private function readableMedia(NodeInterface $node): array {
    if (!$node->get(Knowledge::ATTACHMENTS)->access('view', $this->currentUser())) {
      return [];
    }
    $media = [];
    foreach ($node->get(Knowledge::ATTACHMENTS) as $item) {
      $entity = $item->entity;
      if ($entity instanceof MediaInterface && $entity->bundle() === Knowledge::MEDIA &&
          $entity->access('view', $this->currentUser()) && $entity->get('name')->access('view', $this->currentUser()) &&
          $entity->get(Knowledge::FILE)->access('view', $this->currentUser())) {
        $media[] = $entity;
      }
    }
    return $media;
  }

}
