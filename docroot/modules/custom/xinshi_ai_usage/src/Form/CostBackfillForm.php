<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\xinshi_ai_usage\Service\CostBackfillService;
use Drupal\xinshi_ai_usage\Service\PriceBookException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Reviews a bounded historical cost batch before an audited, atomic submission. */
final class CostBackfillForm extends FormBase {

  public function __construct(protected CostBackfillService $backfill) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_ai_usage.cost_backfill'));
  }

  /** {@inheritdoc} */
  public function getFormId(): string {
    return 'xinshi_ai_usage_cost_backfill';
  }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state, int $version_id = 0): array {
    try {
      $version = $this->backfill->version($version_id);
    }
    catch (PriceBookException $e) {
      if ($e->priceCode === 'access_denied') {
        throw new AccessDeniedHttpException();
      }
      if ($e->priceCode === 'version_not_found') {
        throw new NotFoundHttpException();
      }
      return ['message' => ['#plain_text' => $this->t('请选择已发布且已经结束的采购价卡区间。')]];
    }
    $form_state->set('version_id', $version_id);
    $form['version'] = ['#type' => 'item', '#title' => $this->t('采购价卡'),
      '#plain_text' => $version['site_id'] . ' / ' . $version['book_kind'] . ' / ' . $version['version']];
    $form['range'] = ['#type' => 'item', '#title' => $this->t('生效区间（包含开始，不包含结束）'),
      '#plain_text' => self::timestamp((int) $version['effective_from']) . ' – '
        . self::timestamp((int) $version['effective_to'])];
    $form['source'] = ['#type' => 'item', '#title' => $this->t('来源凭证'),
      '#plain_text' => $version['source_ref'] ?? ''];
    $form['scope'] = ['#markup' => '<p>' . $this->t('仅补算该区间内已发送、已报告用量、由平台付款且原因为无采购价卡的调用。每批最多 200 条。补算保留原成本分录，追加估算成本和审计记录，并更新成本报表；用户积分不变。预览在 15 分钟后过期。') . '</p>'];
    $preview = $form_state->get('preview');
    $result = $form_state->get('result');
    if ($result) {
      $form['result'] = ['#plain_text' => $this->t('本批已补算 @count 条，成本 @amount @currency。重复提交不会再次补算。',
        ['@count' => $result['count'], '@amount' => self::amount($result['amount_micros']),
          '@currency' => $result['currency']])];
    }
    elseif (is_array($preview)) {
      $form['summary'] = ['#plain_text' => $this->t('本批可补算 @count 条，合计 @amount @currency。',
        ['@count' => count($preview['entries']), '@amount' => self::amount($preview['amount_micros']),
          '@currency' => $preview['currency']])];
      $rows = [];
      foreach ($preview['entries'] as $entry) {
        $rows[] = [$entry['attempt_id'] . ' #' . $entry['revision'], $entry['model'],
          self::timestamp($entry['occurred_at']), self::amount($entry['amount_micros']) . ' ' . $preview['currency']];
      }
      $form['calls'] = ['#type' => 'table', '#header' => [$this->t('调用 / 用量版本'),
        $this->t('模型'), $this->t('调用时间'), $this->t('补算成本')], '#rows' => $rows,
        '#empty' => $this->t('本批没有可补算的调用。')];
      if ($preview['skipped']) {
        $skips = [];
        foreach ($preview['skipped'] as $reason => $count) {
          $label = match ($reason) {
            'token_rate_missing' => $this->t('缺少所需的 Token 费率'),
            'invalid_usage' => $this->t('用量数据不完整或无效'),
            'cost_overflow' => $this->t('成本计算超出数值范围'),
            default => $this->t('未匹配有效采购费率'),
          };
          $skips[] = $label . '：' . $count;
        }
        $form['skipped'] = ['#type' => 'item', '#title' => $this->t('本批未补算的原因'),
          '#plain_text' => implode('；', $skips)];
      }
      if ($preview['entries']) {
        $form['reason'] = ['#type' => 'textfield', '#title' => $this->t('补算原因'),
          '#description' => $this->t('填写本次补算的业务依据，最多 255 字节。'), '#required' => TRUE];
        $form['confirm'] = ['#type' => 'checkbox',
          '#title' => $this->t('我已核对本批调用、价卡区间、来源凭证和补算金额。'), '#required' => TRUE];
        $form['execute'] = ['#type' => 'submit', '#name' => 'execute_backfill',
          '#value' => $this->t('确认补算本批历史成本'), '#button_type' => 'primary'];
      }
    }
    $form['preview'] = ['#type' => 'submit', '#name' => 'preview_backfill',
      '#value' => !is_array($preview) ? $this->t('生成补算预览')
        : ($result || (!$preview['entries'] && $preview['next_after_id'] !== NULL)
          ? $this->t('预览剩余调用') : $this->t('重新生成本批预览')),
      '#submit' => ['::previewBatch'], '#limit_validation_errors' => []];
    $form['back'] = ['#type' => 'link', '#title' => $this->t('返回采购价卡'),
      '#url' => Url::fromRoute('xinshi_ai_usage.price_book')];
    return $form;
  }

  /** Generates a new server-side snapshot only on an explicit preview submission. */
  public function previewBatch(array &$form, FormStateInterface $form_state): void {
    try {
      $preview = $this->backfill->preview((int) $form_state->get('version_id'),
        (int) ($form_state->get('after_id') ?? 0));
      $form_state->set('preview', $preview)->set('result', NULL);
      if (!$preview['entries'] && $preview['next_after_id'] !== NULL) {
        $form_state->set('after_id', $preview['next_after_id']);
      }
      $form_state->setValue('confirm', FALSE);
      $input = $form_state->getUserInput() ?? [];
      unset($input['confirm']);
      $form_state->setUserInput($input);
    }
    catch (PriceBookException $e) {
      $this->messenger()->addError($this->errorMessage($e));
    }
    $form_state->setRebuild();
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (($form_state->getTriggeringElement()['#name'] ?? '') !== 'execute_backfill') {
      return;
    }
    if (!$form_state->getValue('confirm') || !is_array($form_state->get('preview'))) {
      $form_state->setErrorByName('confirm', $this->t('请先生成预览并确认本批补算。'));
    }
    $reason = trim((string) $form_state->getValue('reason'));
    if ($reason === '' || strlen($reason) > 255) {
      $form_state->setErrorByName('reason', $this->t('补算原因必须为 1 至 255 字节。'));
    }
  }

  /** Executes the cached preview, never a newly calculated replacement. */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      if (!$form_state->getValue('confirm')) {
        throw new PriceBookException('invalid_preview', 'confirmation is required');
      }
      $preview = $form_state->get('preview');
      $result = $this->backfill->execute(is_array($preview) ? $preview : [],
        (string) $form_state->getValue('reason'));
      $form_state->set('result', $result)->set('after_id', $preview['next_after_id'] ?? 0);
    }
    catch (PriceBookException $e) {
      $this->messenger()->addError($this->errorMessage($e));
    }
    $form_state->setRebuild();
  }

  private function errorMessage(PriceBookException $e): string {
    return (string) match ($e->priceCode) {
      'projection_busy' => $this->t('用量正在处理，请稍后重试本批。'),
      'access_denied' => $this->t('当前账户缺少历史成本补算权限。'),
      'stale_preview', 'invalid_preview' => $this->t('预览已过期、调用已变化或本批校验失败。请重新生成预览并核对。'),
      'invalid_reason' => $this->t('请填写 1 至 255 字节的补算原因。'),
      default => $this->t('补算失败，本批没有提交。请检查价卡和用量后重新预览。'),
    };
  }

  private static function timestamp(int $ms): string {
    return gmdate('Y-m-d\TH:i:s', intdiv($ms, 1000)) . '.'
      . str_pad((string) ($ms % 1000), 3, '0', STR_PAD_LEFT) . '+00:00';
  }

  private static function amount(string $micros): string {
    $digits = str_pad($micros, 7, '0', STR_PAD_LEFT);
    return substr($digits, 0, -6) . '.' . substr($digits, -6);
  }

}
