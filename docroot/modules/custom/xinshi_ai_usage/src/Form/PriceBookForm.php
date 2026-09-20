<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai_usage\Service\CostRatingService;
use Drupal\xinshi_ai_usage\Service\PriceBookException;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Admin form for viewing and creating purchasing price book versions.
 *
 * The form shows the currently active version (if any), a table of recent
 * versions, and a create-draft section. Activating a version closes the
 * previous active one atomically via PriceBookService.
 *
 * This is a first-pass admin UI: rates are edited as JSON textarea so that
 * arbitrarily complex rate structures can be entered without building dozens
 * of form elements. A structured form can replace it later when the rate
 * schema is finalised.
 */
final class PriceBookForm extends FormBase {

  public function __construct(
    private readonly PriceBookService $priceBook,
    private readonly CostRatingService $costRating,
    RequestStack $requestStack,
    private readonly Settings $settings,
  ) {
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xinshi_ai_usage.price_book'),
      $container->get('xinshi_ai_usage.cost_rating'),
      $container->get('request_stack'),
      $container->get('settings'),
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
    $siteId = $this->siteId();
    $kind = PriceBookService::KIND_SUPPLIER_CHAT;
    $active = $this->priceBook->loadActive($siteId, $kind);

    $form['overview'] = [
      '#type' => 'details',
      '#title' => $this->t('当前生效版本'),
      '#open' => TRUE,
    ];
    if ($active === NULL) {
      $form['overview']['none'] = [
        '#markup' => '<p>' . $this->t('当前没有生效的采购价卡，所有模型调用都会标记为未定价（unpriced）。') . '</p>',
      ];
    }
    else {
      $form['overview']['summary'] = [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('版本：@version', ['@version' => $active['version']]),
          $this->t('币种：@currency', ['@currency' => $active['currency']]),
          $this->t('生效开始：@from', ['@from' => date('Y-m-d H:i:s', (int) ($active['effective_from'] / 1000))]),
        ],
      ];
    }

    $form['create'] = [
      '#type' => 'details',
      '#title' => $this->t('新建草稿版本'),
      '#open' => $active === NULL,
      '#description' => $this->t('新建一个版本后会保持草稿状态，确认无误后再点底部的“设为生效版本”切换。切换会立即关闭当前生效版本。'),
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
      '#description' => $this->t('按 accounts → models → per_million_* 结构填写；数值为每百万 token 的 micros。详见架构文档第 5.2 节。'),
      '#rows' => 20,
      '#default_value' => json_encode($this->seedRates(),
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
    $siteId = $this->siteId();
    $kind = PriceBookService::KIND_SUPPLIER_CHAT;
    $version = (string) $form_state->getValue('version');
    $currency = strtoupper((string) $form_state->getValue('currency'));
    $ratesJson = (string) $form_state->getValue('rates_json');
    $sourceRef = (string) $form_state->getValue('source_ref');
    $now = (int) (microtime(TRUE) * 1000);

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
   * Returns the logical site id the price book belongs to.
   *
   * A single-site deployment can leave this as the request host; multi-site
   * deployments should set it in settings.php under
   * $settings['xinshi_ai_usage.site_id'].
   */
  private function siteId(): string {
    $configured = $this->settings->get('xinshi_ai_usage.site_id');
    if (is_string($configured) && $configured !== '') {
      return $configured;
    }
    $request = $this->requestStack->getCurrentRequest();
    return $request?->getHost() ?: 'default';
  }

  /**
   * Returns a small seed rate structure so the JSON textarea is usable.
   *
   * These are example values, not real prices; the admin must replace them.
   */
  private function seedRates(): array {
    return [
      'accounts' => [
        'xinshi' => [
          'models' => [
            'deepseek-v4-flash' => [
              'per_million_input' => 0,
              'per_million_cache_read' => 0,
              'per_million_cache_write' => 0,
              'per_million_output' => 0,
            ],
          ],
        ],
      ],
    ];
  }

}
