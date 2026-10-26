<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\xinshi_ai_usage\Service\CurrencyDisplay;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Configures the display currency and refreshes reference rates. */
final class CurrencyDisplayForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected CurrencyDisplay $currencyDisplay,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('config.factory'), $container->get('config.typed'),
      $container->get('xinshi_ai_usage.currency_display'));
  }

  /** {@inheritdoc} */
  public function getFormId(): string {
    return 'xinshi_ai_usage_currency_display';
  }

  /** {@inheritdoc} */
  protected function getEditableConfigNames(): array {
    return [CurrencyDisplay::CONFIG];
  }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $snapshot = $this->currencyDisplay->snapshot();
    $options = ['CNY' => $this->t('人民币（CNY）'), 'USD' => $this->t('美元（USD）'),
      'EUR' => $this->t('欧元（EUR）')];
    foreach (array_keys($snapshot['rates']) as $currency) {
      $options[$currency] ??= $currency;
    }
    $options[$snapshot['currency']] ??= $snapshot['currency'];
    $form['currency'] = [
      '#type' => 'select', '#title' => $this->t('默认显示币种'),
      '#options' => $options, '#default_value' => $snapshot['currency'], '#required' => TRUE,
      '#description' => $this->t('所有 AI 价格与费用按此币种显示参考金额。原始价卡、费用记录和导出保留原币种。修改后重新加载报表即可生效。'),
    ];
    $form['source'] = ['#markup' => '<p>' . $this->t('数据源：欧洲央行（ECB）每日参考汇率。定时任务每六小时检查更新；失败后每小时重试。周末和休市日沿用最近工作日数据。参考汇率不用于实际扣款或结算。') . '</p>'];
    $form['status'] = ['#markup' => '<p>' . ($snapshot['rate_date'] === NULL
      ? $this->t('尚未获取汇率。请刷新汇率，并确认站点定时任务已启用。缺少汇率的金额将保留原币种并提示。')
      : $this->t('汇率日期：@date。最近获取：@fetched。', [
        '@date' => $snapshot['rate_date'], '@fetched' => $snapshot['fetched_at'],
      ])) . '</p>'];
    if ($snapshot['stale']) {
      $form['stale'] = ['#markup' => '<p>' . $this->t('最近更新失败或汇率日期已超过七天。当前保留上次成功获取的汇率。') . '</p>'];
    }
    $form['rates'] = ['#type' => 'details', '#title' => $this->t('参考汇率（1 EUR 对应的金额）')];
    $form['rates']['table'] = ['#type' => 'table',
      '#header' => [$this->t('币种'), $this->t('汇率')],
      '#rows' => array_map(static fn($code, $rate) => [$code, $rate],
        array_keys($snapshot['rates']), array_values($snapshot['rates'])),
    ];
    $form = parent::buildForm($form, $form_state);
    $form['actions']['refresh'] = ['#type' => 'submit', '#value' => $this->t('刷新汇率'),
      '#submit' => ['::refreshRates'], '#limit_validation_errors' => []];
    return $form;
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(CurrencyDisplay::CONFIG)->set('currency', $form_state->getValue('currency'))->save();
    parent::submitForm($form, $form_state);
  }

  /** Refreshes the saved rates without saving an unsubmitted currency choice. */
  public function refreshRates(array &$form, FormStateInterface $form_state): void {
    if ($this->currencyDisplay->refresh(TRUE)) {
      $this->messenger()->addStatus($this->t('参考汇率已更新。'));
    }
    else {
      $this->messenger()->addError($this->t('汇率暂时无法更新或已有更新正在执行。已保留原有数据，请稍后重试。'));
    }
    $form_state->setRebuild();
  }

}
