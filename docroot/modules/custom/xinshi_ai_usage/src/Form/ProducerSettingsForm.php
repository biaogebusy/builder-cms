<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Registers usage producers at /admin/config/xinshi/ai/usage.
 *
 * The generated secret is shown exactly once, together with the environment
 * variables the Node chat service needs; it is stored encrypted and never
 * displayed again. Registering an existing ID rotates its secret.
 */
final class ProducerSettingsForm extends FormBase {

  public const DEFAULT_PRODUCER = 'chat-node';

  public function __construct(
    private readonly ProducerVault $vault,
    private readonly Settings $settings,
    RequestStack $requestStack,
    private readonly TimeInterface $time,
    private readonly DateFormatterInterface $dateFormatter,
  ) {
    // FormBase already declares $requestStack; promoting it again is fatal.
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xinshi_ai_usage.producer_vault'),
      $container->get('settings'),
      $container->get('request_stack'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'xinshi_ai_usage_producers';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('生产者是向本站上报 AI 用量事件的服务，例如信使对话服务（Node）。每个生产者持有独立密钥，事件中的站点标识必须与这里登记的一致。') . '</p>',
    ];
    $form['producers'] = [
      '#type' => 'table',
      '#header' => [$this->t('生产者 ID'), $this->t('站点标识'), $this->t('来源'), $this->t('登记时间'), $this->t('操作')],
      '#empty' => $this->t('尚未登记生产者。'),
    ];
    $configured = $this->settings->get('xinshi_ai_usage.producers', []);
    foreach (is_array($configured) ? $configured : [] as $id => $producer) {
      $form['producers'][$id] = [
        'id' => ['#plain_text' => (string) $id],
        'site_id' => ['#plain_text' => (string) (is_array($producer) ? ($producer['site_id'] ?? '') : '')],
        'source' => ['#plain_text' => $this->t('settings.php（优先于后台登记）')],
        'created' => ['#plain_text' => '-'],
        'delete' => ['#plain_text' => ''],
      ];
    }
    foreach ($this->vault->all() as $id => $producer) {
      if (isset($form['producers'][$id])) {
        // settings.php overrides the registry; only show the effective source.
        continue;
      }
      $form['producers'][$id] = [
        'id' => ['#plain_text' => $id],
        'site_id' => ['#plain_text' => $producer['site_id']],
        'source' => ['#plain_text' => $this->t('后台登记')],
        'created' => ['#plain_text' => $producer['created_at'] > 0 ? $this->dateFormatter->format($producer['created_at'], 'short') : '-'],
        'delete' => [
          '#type' => 'submit',
          '#value' => $this->t('删除'),
          '#name' => 'delete_' . $id,
          '#producer_id' => $id,
          '#submit' => ['::deleteProducer'],
          '#limit_validation_errors' => [],
        ],
      ];
    }

    $request = $this->getRequest();
    $form['register'] = [
      '#type' => 'details',
      '#title' => $this->t('登记或轮换生产者密钥'),
      '#open' => TRUE,
      '#description' => $this->t('保存后生成新密钥并只显示一次；对已登记的 ID 重新保存即轮换密钥，旧密钥立即失效。'),
    ];
    $form['register']['producer_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('生产者 ID'),
      '#description' => $this->t('与 Node 的 METERING_PRODUCER_ID 一致，默认 chat-node。'),
      '#default_value' => self::DEFAULT_PRODUCER,
      '#maxlength' => 64,
      '#required' => TRUE,
    ];
    $form['register']['site_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('站点标识'),
      '#description' => $this->t('逻辑站点标签，用于标记和隔离事件，不是地址。必须与 Node 的 METERING_SITE_ID 完全一致，登记后不要再改。Node 未设置该变量时默认取其 apiUrl 的主机名；前端与后端域名不同时，建议两边都显式填写前端产品域名。'),
      '#default_value' => $request?->getHost() ?: '',
      '#maxlength' => 128,
      '#required' => TRUE,
    ];
    $form['register']['actions'] = ['#type' => 'actions'];
    $form['register']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('保存并生成密钥'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!preg_match(ProducerVault::PRODUCER_PATTERN, (string) $form_state->getValue('producer_id'))) {
      $form_state->setErrorByName('producer_id', $this->t('生产者 ID 只能包含字母、数字、点、下划线和连字符，最长 64 个字符。'));
    }
    if (!preg_match(ProducerVault::SITE_PATTERN, (string) $form_state->getValue('site_id'))) {
      $form_state->setErrorByName('site_id', $this->t('站点标识只能包含可见 ASCII 字符，最长 128 个字符。'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = (string) $form_state->getValue('producer_id');
    $siteId = (string) $form_state->getValue('site_id');
    $secret = ProducerVault::generateSecret();
    $this->vault->set($id, $secret, $siteId, $this->time->getRequestTime());
    $this->messenger()->addStatus(new FormattableMarkup(
      '<p>@intro</p><pre>METERING_INGEST_SECRET=@secret<br>METERING_PRODUCER_ID=@producer<br>METERING_SITE_ID=@site</pre>',
      [
        '@intro' => $this->t('生产者 @id 的密钥已生成，只显示这一次。请写入 Node 对话服务的环境变量后重启该服务：', ['@id' => $id]),
        '@secret' => $secret, '@producer' => $id, '@site' => $siteId,
      ]
    ));
    $configured = $this->settings->get('xinshi_ai_usage.producers', []);
    if (is_array($configured) && isset($configured[$id])) {
      $this->messenger()->addWarning($this->t('settings.php 已定义生产者 @id，将继续优先使用那里的密钥；移除该条目后后台登记才会生效。', ['@id' => $id]));
    }
  }

  /** Removes a registry entry; a producer that is still running loses access at once. */
  public function deleteProducer(array &$form, FormStateInterface $form_state): void {
    $id = (string) ($form_state->getTriggeringElement()['#producer_id'] ?? '');
    if ($id !== '' && $this->vault->get($id) !== NULL) {
      $this->vault->delete($id);
      $this->messenger()->addStatus($this->t('已删除生产者 @id；使用该密钥的服务将无法继续上报。', ['@id' => $id]));
    }
  }

}
