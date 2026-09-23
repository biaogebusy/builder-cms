<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\xinshi_ai\Service\HarnessSettings;

/**
 * Xinshi AI 模块全局配置:/admin/config/xinshi/ai。
 *
 * 模型 / 平台清单不在这里管理(走 xinshi_ai.models,详见模块文档 §5)。
 */
final class SettingsForm extends ConfigFormBase {

  private const CONFIG_NAME = 'xinshi_ai.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'xinshi_ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::CONFIG_NAME);

    $form['gateway'] = [
      '#type' => 'details',
      '#title' => $this->t('信使网关'),
      '#open' => TRUE,
    ];
    $form['gateway']['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('网关 base URL'),
      '#description' => $this->t('New API(OpenAI-compatible)网关地址,如 https://ai.builder.design。'),
      '#default_value' => $config->get('gateway.base_url'),
      '#required' => TRUE,
    ];
    $form['gateway']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('网关 API key'),
      '#description' => $this->t('New API 网关的访问 key(各项目独立)。保存在配置中,后端调用网关时作为 Bearer token 使用。'),
      '#default_value' => $config->get('gateway.api_key'),
      '#required' => TRUE,
    ];
    $form['gateway']['request_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('上游请求超时(秒)'),
      '#description' => $this->t('调用网关生图的 HTTP 超时。上游生图可能耗时近百秒,默认 180。'),
      '#min' => 1,
      '#default_value' => $config->get('gateway.request_timeout'),
      '#required' => TRUE,
    ];

    $harness = HarnessSettings::normalize($config->get('harness'));
    $form['harness'] = [
      '#type' => 'details',
      '#title' => $this->t('对话任务（Harness）'),
      '#description' => $this->t('若对话服务设置了对应环境变量，则以环境变量为准。移除对应启动参数后，后台设置才会生效。'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['harness']['tools'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('业务工具'),
    ];
    $form['harness']['tools']['pages_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('启用页面草稿工具'),
      '#description' => $this->t('允许 AI 读取、创建、追加组件和删除本人草稿；仍需相应页面权限，写入仍需用户确认。关闭后仍可核验已提交操作。对应 CHAT_CREATE_PAGE_ENABLED。'),
      '#default_value' => $harness['tools']['pages_enabled'],
    ];
    $form['harness']['task_limits'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('任务限制'),
      '#description' => $this->t('仅影响新建任务。已有任务在审批、继续执行和服务重启后，仍保留原上限及已用次数。'),
    ];
    $form['harness']['task_limits']['max_model_calls'] = [
      '#type' => 'number',
      '#title' => $this->t('每个任务最多模型调用次数'),
      '#description' => $this->t('包含规划、执行、评审、工具内部调用及显式重试。达到上限后停止发起新的模型请求。对应 CHAT_TASK_MAX_MODEL_CALLS。'),
      '#default_value' => $harness['task_limits']['max_model_calls'],
      '#min' => 1,
      '#max' => HarnessSettings::MAX_LIMIT,
      '#step' => 1,
      '#required' => TRUE,
    ];
    $form['harness']['task_limits']['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('每个任务最多已报告 Token'),
      '#description' => $this->t('按模型实际报告的输入与输出 Token 累计。达到上限后停止下一次调用，单次响应可能超过此值。对应 CHAT_TASK_MAX_TOKENS。'),
      '#default_value' => $harness['task_limits']['max_tokens'],
      '#min' => 1,
      '#max' => HarnessSettings::MAX_LIMIT,
      '#step' => 1,
      '#required' => TRUE,
    ];

    $form['image'] = [
      '#type' => 'details',
      '#title' => $this->t('图片任务'),
      '#open' => TRUE,
    ];
    $form['image']['default_n'] = [
      '#type' => 'number',
      '#title' => $this->t('默认生成张数'),
      '#description' => $this->t('单次任务默认生成张数;具体上界由模型的 max_n 决定。'),
      '#min' => 1,
      '#default_value' => $config->get('image.default_n'),
    ];

    $form['queue'] = [
      '#type' => 'details',
      '#title' => $this->t('队列消费'),
      '#open' => TRUE,
    ];
    $form['queue']['worker'] = [
      '#type' => 'select',
      '#title' => $this->t('消费方式'),
      '#options' => [
        'daemon' => $this->t('常驻消费者(supervisor 跑 drush queue:run)— 生产推荐'),
        'cron' => $this->t('Cron + 响应后即时消费(在 PHP-FPM 进程内处理)'),
      ],
      '#description' => $this->t('daemon:不在 web 进程处理,需配置 supervisor 常驻消费者,以 web 用户身份运行。cron:创建任务的请求结束后在 PHP-FPM 进程内即时消费(开发便利,但会占住 worker,且该进程用户须能写 media 目录)。'),
      '#default_value' => $config->get('queue.worker') ?: 'daemon',
    ];

    $form['event_ticket'] = [
      '#type' => 'details',
      '#title' => $this->t('事件票据(SSE 鉴权)'),
    ];    $form['event_ticket']['ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('TTL(秒)'),
      '#min' => 1,
      '#default_value' => $config->get('event_ticket.ttl'),
    ];

    $form['sse'] = [
      '#type' => 'details',
      '#title' => $this->t('SSE'),
    ];
    $form['sse']['heartbeat_seconds'] = [
      '#type' => 'number',
      '#title' => $this->t('心跳间隔(秒)'),
      '#min' => 1,
      '#default_value' => $config->get('sse.heartbeat_seconds'),
    ];
    $form['sse']['max_lifetime_seconds'] = [
      '#type' => 'number',
      '#title' => $this->t('最大存活(秒)'),
      '#min' => 1,
      '#default_value' => $config->get('sse.max_lifetime_seconds'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(self::CONFIG_NAME)
      ->set('gateway.base_url', $form_state->getValue('base_url'))
      ->set('gateway.api_key', $form_state->getValue('api_key'))
      ->set('gateway.request_timeout', (int) $form_state->getValue('request_timeout'))
      ->set('image.default_n', (int) $form_state->getValue('default_n'))
      ->set('queue.worker', $form_state->getValue('worker'))
      ->set('event_ticket.ttl', (int) $form_state->getValue('ttl'))
      ->set('sse.heartbeat_seconds', (int) $form_state->getValue('heartbeat_seconds'))
      ->set('sse.max_lifetime_seconds', (int) $form_state->getValue('max_lifetime_seconds'))
      ->set('harness.tools.pages_enabled', (bool) $form_state->getValue(['harness', 'tools', 'pages_enabled']))
      ->set('harness.task_limits.max_model_calls', (int) $form_state->getValue(['harness', 'task_limits', 'max_model_calls']))
      ->set('harness.task_limits.max_tokens', (int) $form_state->getValue(['harness', 'task_limits', 'max_tokens']))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
