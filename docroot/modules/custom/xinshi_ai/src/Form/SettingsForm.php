<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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
      ->save();

    parent::submitForm($form, $form_state);
  }

}
