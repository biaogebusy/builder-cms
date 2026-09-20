<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai_usage\Contract\UsageEventValidator;
use Drupal\xinshi_ai_usage\Service\PriceBookException;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Admin form for viewing, creating and activating purchasing price book versions.
 *
 * Price books are keyed by the same logical site id that usage events carry,
 * which is the site id registered with each producer (or the
 * `xinshi_ai_usage.site_id` setting). The request host is only a last resort
 * before any producer exists, because the CMS host usually differs from the
 * frontend host the producer reports.
 *
 * This is a first-pass admin UI: rates are edited as JSON textarea so that
 * arbitrarily complex rate structures can be entered without building dozens
 * of form elements. A structured form can replace it later when the rate
 * schema is finalised.
 */
final class PriceBookForm extends FormBase {

  public function __construct(
    private readonly PriceBookService $priceBook,
    private readonly ProducerVault $vault,
    RequestStack $requestStack,
    private readonly Settings $settings,
    private readonly TimeInterface $time,
  ) {
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xinshi_ai_usage.price_book'),
      $container->get('xinshi_ai_usage.producer_vault'),
      $container->get('request_stack'),
      $container->get('settings'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'xinshi_ai_usage_price_book';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $kind = PriceBookService::KIND_SUPPLIER_CHAT;
    $sites = $this->siteIds();
    $registered = $sites['registered'];
    $siteIds = $sites['ids'];

    $form['overview'] = [
      '#type' => 'details',
      '#title' => $this->t('当前生效版本'),
      '#open' => TRUE,
    ];
    if (!$registered) {
      $form['overview']['unregistered'] = [
        '#markup' => '<p>' . $this->t('尚未登记任何用量生产者，价卡暂时按当前后台域名 @site 保存。请先在“用量计量”标签登记生产者，价卡会按登记的站点标识匹配事件。', ['@site' => $siteIds[0]]) . '</p>',
      ];
    }
    $rows = [];
    foreach ($siteIds as $siteId) {
      $active = $this->priceBook->loadActive($siteId, $kind);
      $rows[] = [
        $siteId,
        $active === NULL ? $this->t('无（所有调用标记为未定价）') : $active['version'],
        $active === NULL ? '' : $active['currency'],
        $active === NULL ? '' : $this->formatMs($active['effective_from']),
      ];
    }
    $form['overview']['active'] = [
      '#type' => 'table',
      '#header' => [$this->t('站点标识'), $this->t('生效版本'), $this->t('币种'), $this->t('生效开始')],
      '#rows' => $rows,
    ];

    $form['versions'] = [
      '#type' => 'details',
      '#title' => $this->t('版本列表'),
      '#open' => TRUE,
      '#description' => $this->t('草稿可在此设为生效；切换从激活时刻起生效并关闭当前版本，不回溯已发生的调用。'),
    ];
    $form['versions']['table'] = [
      '#type' => 'table',
      '#header' => [$this->t('站点标识'), $this->t('版本'), $this->t('币种'), $this->t('状态'),
        $this->t('生效区间'), $this->t('来源凭证'), $this->t('操作')],
      '#empty' => $this->t('还没有任何版本。'),
    ];
    foreach ($siteIds as $siteId) {
      foreach ($this->priceBook->listVersions($siteId, $kind) as $version) {
        $row = &$form['versions']['table'][$siteId . ':' . $version['id']];
        $row['site'] = ['#plain_text' => $siteId];
        $row['version'] = ['#plain_text' => $version['version']];
        $row['currency'] = ['#plain_text' => $version['currency']];
        $row['status'] = ['#plain_text' => $this->statusLabel($version)];
        $row['range'] = ['#plain_text' => $version['status'] === PriceBookService::STATUS_ACTIVE
          ? $this->formatMs($version['effective_from']) . ' – '
            . ($version['effective_to'] === NULL ? $this->t('至今') : $this->formatMs($version['effective_to']))
          : ''];
        $row['source_ref'] = ['#plain_text' => $version['source_ref'] ?? ''];
        $row['activate'] = $version['status'] === PriceBookService::STATUS_DRAFT ? [
          '#type' => 'submit',
          '#value' => $this->t('设为生效'),
          '#name' => 'activate:' . $siteId . ':' . $version['id'],
          '#version_id' => $version['id'],
          '#site_id' => $siteId,
          '#submit' => ['::activateVersion'],
          '#limit_validation_errors' => [],
        ] : ['#plain_text' => ''];
        unset($row);
      }
    }

    $form['create'] = [
      '#type' => 'details',
      '#title' => $this->t('新建草稿版本'),
      '#open' => TRUE,
      '#description' => $this->t('新建的版本保持草稿状态，确认无误后在版本列表点“设为生效”，或直接点“保存并设为生效版本”。'),
    ];
    $form['create']['site_id'] = [
      '#type' => 'select',
      '#title' => $this->t('站点标识'),
      '#description' => $this->t('与“用量计量”标签登记生产者时填写的站点标识一致；事件按此值匹配价卡。'),
      '#options' => array_combine($siteIds, $siteIds),
      '#default_value' => $siteIds[0],
      '#required' => TRUE,
    ];
    $form['create']['version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('版本号'),
      '#description' => $this->t('同一站点同一价卡类型下不可重复，例如 2026-09-v1。'),
      '#maxlength' => 64,
      '#required' => TRUE,
    ];
    $form['create']['currency'] = [
      '#type' => 'textfield',
      '#title' => $this->t('币种'),
      '#description' => $this->t('ISO 4217 代码，例如 CNY、USD。金额单位为该币种的 micros（1 单位 = 1,000,000 micros）。'),
      '#default_value' => 'CNY',
      '#maxlength' => 8,
      '#required' => TRUE,
    ];
    $form['create']['rates_json'] = [
      '#type' => 'textarea',
      '#title' => $this->t('费率 JSON'),
      '#description' => $this->t('按 accounts → models → per_million_* 结构填写；数值为每百万 token 的 micros。预填内容只是格式示例，必须按供应商报价改成真实费率后才能保存；显式填 0 表示该项免费。详见架构文档第 5.2 节。'),
      '#rows' => 20,
      '#default_value' => json_encode(self::seedRates(),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      '#required' => TRUE,
    ];
    $form['create']['source_ref'] = [
      '#type' => 'textfield',
      '#title' => $this->t('来源凭证'),
      '#description' => $this->t('可选。供应商报价单、发票或合同编号，便于对账时追溯费率来源。'),
      '#maxlength' => 255,
    ];

    $form['create']['actions'] = ['#type' => 'actions'];
    $form['create']['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('保存为草稿'),
      '#button_type' => 'primary',
      '#name' => 'save_draft',
    ];
    $form['create']['actions']['activate'] = [
      '#type' => 'submit',
      '#value' => $this->t('保存并设为生效版本'),
      '#name' => 'save_and_activate',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $json = (string) $form_state->getValue('rates_json');
    $decoded = json_decode($json, TRUE);
    if (!is_array($decoded)) {
      $form_state->setErrorByName('rates_json', $this->t('费率 JSON 格式错误。'));
      return;
    }
    // The pre-filled example must not become a real price book: an unedited
    // submit would rate the example model at made-up prices.
    if (UsageEventValidator::canonicalJson($decoded) === UsageEventValidator::canonicalJson(self::seedRates())) {
      $form_state->setErrorByName('rates_json', $this->t('费率 JSON 仍是预填的示例，请按供应商报价填写真实费率。'));
      return;
    }
    try {
      $this->priceBook->parseRates($json);
    }
    catch (PriceBookException $e) {
      $form_state->setErrorByName('rates_json',
        $this->t('费率结构错误：@message', ['@message' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $siteId = (string) $form_state->getValue('site_id');
    if (!in_array($siteId, $this->siteIds()['ids'], TRUE)) {
      $this->messenger()->addError($this->t('站点标识 @site 未登记。', ['@site' => $siteId]));
      return;
    }
    $kind = PriceBookService::KIND_SUPPLIER_CHAT;
    $version = (string) $form_state->getValue('version');
    $currency = strtoupper((string) $form_state->getValue('currency'));
    $ratesJson = (string) $form_state->getValue('rates_json');
    $sourceRef = (string) $form_state->getValue('source_ref');
    // Same clock as PriceBookService, so activation and lookup agree on "now".
    $now = (int) round($this->time->getCurrentMicroTime() * 1000);

    try {
      $id = $this->priceBook->createDraft($siteId, $kind, $version, $currency, $ratesJson, $now,
        $sourceRef, (string) $this->currentUser()->id());
    }
    catch (PriceBookException $e) {
      $this->messenger()->addError($e->getMessage());
      return;
    }

    $trigger = $form_state->getTriggeringElement()['#name'] ?? '';
    if ($trigger === 'save_and_activate') {
      try {
        $this->priceBook->activate($id, $siteId, $kind);
        $this->messenger()->addStatus($this->t('版本 @version 已设为生效。', ['@version' => $version]));
      }
      catch (PriceBookException $e) {
        $this->messenger()->addError($this->t('草稿已保存，但启用失败：@message', ['@message' => $e->getMessage()]));
      }
    }
    else {
      $this->messenger()->addStatus($this->t('已保存草稿版本 @version。', ['@version' => $version]));
    }
  }

  /**
   * Submit handler of the per-row "activate" buttons in the version list.
   */
  public function activateVersion(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $versionId = (int) ($trigger['#version_id'] ?? 0);
    $siteId = (string) ($trigger['#site_id'] ?? '');
    if ($versionId <= 0 || !in_array($siteId, $this->siteIds()['ids'], TRUE)) {
      $this->messenger()->addError($this->t('无法识别要启用的版本。'));
      return;
    }
    try {
      $this->priceBook->activate($versionId, $siteId, PriceBookService::KIND_SUPPLIER_CHAT);
      $this->messenger()->addStatus($this->t('版本已设为生效。'));
    }
    catch (PriceBookException $e) {
      $this->messenger()->addError($this->t('启用失败：@message', ['@message' => $e->getMessage()]));
    }
  }

  /**
   * Site ids price books can be created for, in display order.
   *
   * Explicit `xinshi_ai_usage.site_id` wins; otherwise the distinct site ids of
   * the registered producers (settings.php entries and the admin registry).
   * With no producer at all the request host is used and the form says so.
   *
   * @return array{ids:list<string>,registered:bool}
   */
  private function siteIds(): array {
    $configured = $this->settings->get('xinshi_ai_usage.site_id');
    if (is_string($configured) && $configured !== '') {
      return ['ids' => [$configured], 'registered' => TRUE];
    }
    $ids = [];
    $producers = $this->settings->get('xinshi_ai_usage.producers', []);
    $producers = (is_array($producers) ? $producers : []) + $this->vault->all();
    foreach ($producers as $producer) {
      $siteId = is_array($producer) ? ($producer['site_id'] ?? NULL) : NULL;
      if (is_string($siteId) && $siteId !== '') {
        $ids[$siteId] = $siteId;
      }
    }
    if ($ids) {
      return ['ids' => array_values($ids), 'registered' => TRUE];
    }
    $request = $this->requestStack->getCurrentRequest();
    return ['ids' => [$request?->getHost() ?: 'default'], 'registered' => FALSE];
  }

  private function statusLabel(array $version): string {
    if ($version['status'] !== PriceBookService::STATUS_ACTIVE) {
      return (string) $this->t('草稿');
    }
    return (string) ($version['effective_to'] === NULL ? $this->t('生效中') : $this->t('已关闭'));
  }

  private function formatMs(int $ms): string {
    return gmdate('Y-m-d H:i:s', intdiv($ms, 1000)) . ' UTC';
  }

  /**
   * Format example only; validateForm() refuses to save it unchanged.
   *
   * Values follow the architecture document's rate example so the admin sees
   * realistic magnitudes (micros per million tokens), not zeros.
   */
  public static function seedRates(): array {
    return [
      'accounts' => [
        'xinshi' => [
          'models' => [
            'example-model' => [
              'per_million_input' => 2_000_000,
              'per_million_cache_read' => 200_000,
              'per_million_cache_write' => 1_000_000,
              'per_million_output' => 8_000_000,
            ],
          ],
        ],
      ],
    ];
  }

}
